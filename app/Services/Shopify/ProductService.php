<?php

namespace App\Services\Shopify;

use App\Exceptions\ShopifyApiException;
use Illuminate\Support\Facades\Log;

class ProductService extends ShopifyService
{
    /**
     * Ürünleri listele (pagination ile)
     * Shopify GraphQL Admin API 2024-10 standardına uygun
     */
    public function getProducts(string $shopDomain, int $first = 20, ?string $after = null): array
    {
        // Cost bilgisi için tüm variant'ları al (cost range hesaplamak için)
        $query = <<<'GRAPHQL'
            query GetProducts($first: Int!, $after: String) {
              products(first: $first, after: $after) {
                pageInfo {
                  hasNextPage
                  endCursor
                }
                edges {
                  node {
                    id
                    title
                    status
                    totalVariants
                    priceRangeV2 {
                      minVariantPrice {
                        amount
                        currencyCode
                      }
                      maxVariantPrice {
                        amount
                        currencyCode
                      }
                    }
                    variants(first: 250) {
                      edges {
                        node {
                          id
                          price
                          inventoryItem {
                            id
                            unitCost {
                              amount
                              currencyCode
                            }
                          }
                        }
                      }
                    }
                  }
                }
              }
            }
        GRAPHQL;

        $variables = [
            'first' => $first,
            'after' => $after,
        ];

        try {
            $data = $this->executeGraphQL($shopDomain, $query, $variables);
            
            $products = $data['products']['edges'] ?? [];
            
            // Her ürün için cost range hesapla
            foreach ($products as &$edge) {
                $product = &$edge['node'];
                $variants = $product['variants']['edges'] ?? [];
                
                $costs = [];
                foreach ($variants as $variantEdge) {
                    $variant = $variantEdge['node'] ?? null;
                    if ($variant && isset($variant['inventoryItem']['unitCost']['amount'])) {
                        $costAmount = (float) $variant['inventoryItem']['unitCost']['amount'];
                        if ($costAmount > 0) {
                            $costs[] = $costAmount;
                        }
                    }
                }
                
                // Cost range hesapla
                if (!empty($costs)) {
                    $minCost = min($costs);
                    $maxCost = max($costs);
                    
                    // Currency code'u cost'u olan ilk variant'tan al
                    $currencyCode = 'USD'; // Default
                    foreach ($variants as $variantEdge) {
                        $variant = $variantEdge['node'] ?? null;
                        if ($variant && isset($variant['inventoryItem']['unitCost']['currencyCode'])) {
                            $currencyCode = $variant['inventoryItem']['unitCost']['currencyCode'];
                            break;
                        }
                    }
                    
                    $product['costRange'] = [
                        'minCost' => $minCost,
                        'maxCost' => $maxCost,
                        'currencyCode' => $currencyCode,
                    ];
                } else {
                    $product['costRange'] = null;
                }
            }
            
            return [
                'products' => $products,
                'pageInfo' => $data['products']['pageInfo'] ?? ['hasNextPage' => false, 'endCursor' => null],
            ];
        } catch (ShopifyApiException $e) {
            Log::error('Failed to fetch products', [
                'shop' => $shopDomain,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Ürün detayı ve variant'ları getir
     * Tüm price, cost ve inventory bilgileri dahil
     * Shopify GraphQL Admin API 2024-10 standardına uygun
     */
    public function getProductWithVariants(string $shopDomain, string $productId, ?string $locationId = null): array
    {
        // Location ID'yi al (eğer verilmediyse)
        if ($locationId === null) {
            $locationId = $this->getShopLocationId($shopDomain);
        }
        
        $query = <<<'GRAPHQL'
            query GetProductVariants($id: ID!) {
              product(id: $id) {
                id
                title
                status
                handle
                priceRangeV2 {
                  minVariantPrice {
                    amount
                    currencyCode
                  }
                  maxVariantPrice {
                    amount
                    currencyCode
                  }
                }
                variants(first: 250) {
                  edges {
                    node {
                      id
                      sku
                      title
                      price
                      compareAtPrice
                      inventoryItem {
                        id
                        unitCost {
                          amount
                          currencyCode
                        }
                        tracked
                        inventoryLevels(first: 10) {
                          edges {
                            node {
                              id
                              location {
                                id
                              }
                              quantities(names: ["available", "on_hand"]) {
                                name
                                quantity
                              }
                            }
                          }
                        }
                      }
                      inventoryQuantity
                      inventoryPolicy
                    }
                  }
                }
              }
            }
        GRAPHQL;

        $variables = ['id' => $productId];
        
        try {
            $data = $this->executeGraphQL($shopDomain, $query, $variables);
            
            if (empty($data['product'])) {
                Log::warning('Product not found', [
                    'shop' => $shopDomain,
                    'productId' => $productId,
                ]);
                throw new ShopifyApiException('Product not found');
            }
            
            $product = $data['product'];
            
            // Location ID'ye göre inventoryLevel bilgilerini variant'lara ekle
            if ($locationId) {
                foreach ($product['variants']['edges'] ?? [] as $edgeIndex => $edge) {
                    $variant = $edge['node'];
                    $inventoryItem = $variant['inventoryItem'] ?? null;
                    
                    if ($inventoryItem && isset($inventoryItem['inventoryLevels']['edges'])) {
                        $inventoryLevels = $inventoryItem['inventoryLevels']['edges'];
                        
                        // Seçilen location'a göre inventoryLevel'ı bul
                        $selectedLocationOnHand = 0;
                        $selectedLocationAvailable = 0;
                        $totalOnHand = 0;
                        $totalAvailable = 0;
                        
                        foreach ($inventoryLevels as $levelEdge) {
                            $level = $levelEdge['node'] ?? null;
                            if ($level && isset($level['location']['id'])) {
                                $levelLocationId = $level['location']['id'];
                                
                                // Quantities'leri parse et - güvenli şekilde
                                $quantities = $level['quantities'] ?? [];
                                $levelOnHand = 0;
                                $levelAvailable = 0;
                                
                                // Debug: quantities array'ini kontrol et (log serialization limitinden kaçınmak için sadece type ve count)
                                $quantitiesType = gettype($quantities);
                                $quantitiesCount = is_array($quantities) ? count($quantities) : 0;
                                
                                if (is_array($quantities) && !empty($quantities)) {
                                    foreach ($quantities as $qtyIndex => $qty) {
                                        // Güvenli parse - array kontrolü
                                        if (is_array($qty) && isset($qty['name']) && isset($qty['quantity'])) {
                                            $qtyName = $qty['name'];
                                            $qtyValue = $qty['quantity'];
                                            
                                            // String değerleri integer'a çevir
                                            if ($qtyName === 'on_hand') {
                                                $levelOnHand = is_numeric($qtyValue) ? (int) $qtyValue : 0;
                                                $totalOnHand += $levelOnHand;
                                            } elseif ($qtyName === 'available') {
                                                $levelAvailable = is_numeric($qtyValue) ? (int) $qtyValue : 0;
                                                $totalAvailable += $levelAvailable;
                                            }
                                        } else {
                                            // Parse edilemeyen quantity formatını logla
                                            Log::warning('Invalid quantity format', [
                                                'shop' => $shopDomain,
                                                'locationId' => $levelLocationId,
                                                'qtyIndex' => $qtyIndex,
                                                'qtyType' => gettype($qty),
                                                'qtyKeys' => is_array($qty) ? array_keys($qty) : 'not_array',
                                            ]);
                                        }
                                    }
                                } else {
                                    // Quantities boş veya geçersiz
                                    Log::debug('Quantities empty or invalid', [
                                        'shop' => $shopDomain,
                                        'locationId' => $levelLocationId,
                                        'quantitiesType' => $quantitiesType,
                                        'quantitiesCount' => $quantitiesCount,
                                    ]);
                                }
                                
                                // Seçilen location ise değerleri kaydet
                                if ($levelLocationId === $locationId) {
                                    $selectedLocationOnHand = $levelOnHand;
                                    $selectedLocationAvailable = $levelAvailable;
                                    
                                    Log::debug('Selected location inventory found', [
                                        'shop' => $shopDomain,
                                        'variantId' => $variant['id'] ?? null,
                                        'locationId' => $locationId,
                                        'onHand' => $selectedLocationOnHand,
                                        'available' => $selectedLocationAvailable,
                                    ]);
                                }
                            }
                        }
                        
                        // Variant'a direkt array'e yaz (reference ile değil, index ile)
                        $product['variants']['edges'][$edgeIndex]['node']['inventoryOnHand'] = $selectedLocationOnHand;
                        $product['variants']['edges'][$edgeIndex]['node']['inventoryAvailable'] = $selectedLocationAvailable;
                        $product['variants']['edges'][$edgeIndex]['node']['totalInventoryOnHand'] = $totalOnHand;
                        $product['variants']['edges'][$edgeIndex]['node']['totalInventoryAvailable'] = $totalAvailable;
                        
                        Log::debug('Variant inventory calculated', [
                            'shop' => $shopDomain,
                            'variantId' => $variant['id'] ?? null,
                            'selectedLocationId' => $locationId,
                            'selectedOnHand' => $selectedLocationOnHand,
                            'selectedAvailable' => $selectedLocationAvailable,
                            'totalOnHand' => $totalOnHand,
                            'totalAvailable' => $totalAvailable,
                        ]);
                    } else {
                        // Inventory item yoksa veya inventoryLevels yoksa 0 set et
                        $product['variants']['edges'][$edgeIndex]['node']['inventoryOnHand'] = 0;
                        $product['variants']['edges'][$edgeIndex]['node']['inventoryAvailable'] = 0;
                        $product['variants']['edges'][$edgeIndex]['node']['totalInventoryOnHand'] = 0;
                        $product['variants']['edges'][$edgeIndex]['node']['totalInventoryAvailable'] = 0;
                    }
                }
            }
            
            // Debug: Response'u logla (quantities array'ini loglamadan)
            $firstVariant = $product['variants']['edges'][0]['node'] ?? null;
            $firstVariantInventory = null;
            if ($firstVariant && isset($firstVariant['inventoryItem']['inventoryLevels']['edges'])) {
                $firstLevels = $firstVariant['inventoryItem']['inventoryLevels']['edges'];
                $firstVariantInventory = [];
                foreach ($firstLevels as $lev) {
                    $levNode = $lev['node'] ?? null;
                    if ($levNode) {
                        $firstVariantInventory[] = [
                            'locationId' => $levNode['location']['id'] ?? null,
                            'quantitiesCount' => count($levNode['quantities'] ?? []),
                        ];
                    }
                }
            }
            
            Log::debug('Product data retrieved', [
                'shop' => $shopDomain,
                'productId' => $productId,
                'variantsCount' => count($product['variants']['edges'] ?? []),
                'locationId' => $locationId,
                'firstVariantInventoryLevels' => $firstVariantInventory,
            ]);
            
            return $product;
        } catch (ShopifyApiException $e) {
            Log::error('Failed to fetch product with variants', [
                'shop' => $shopDomain,
                'productId' => $productId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Product variant price güncelle (selling price)
     * Shopify GraphQL Admin API 2024-10 standardına uygun
     * Not: 2024-04'ten itibaren ProductInput içinde variants field'ı kaldırıldı
     * Artık productVariantsBulkUpdate mutation'ı kullanılmalı
     */
    public function updateVariantPrice(
        string $shopDomain,
        string $productId,
        string $variantId,
        string $price
    ): array {
        // Shopify 2024-04+ API'sinde variant price güncellemek için
        // productVariantsBulkUpdate mutation'ı kullanılmalı
        
        $query = <<<'GRAPHQL'
            mutation UpdateVariantPrice($productId: ID!, $variants: [ProductVariantsBulkInput!]!) {
              productVariantsBulkUpdate(productId: $productId, variants: $variants) {
                productVariants {
                  id
                  price
                }
                userErrors {
                  field
                  message
                }
              }
            }
        GRAPHQL;

        $variables = [
            'productId' => $productId,
            'variants' => [
                [
                    'id' => $variantId,
                    'price' => $price,
                ],
            ],
        ];

        Log::info('Updating variant price', [
            'shop' => $shopDomain,
            'productId' => $productId,
            'variantId' => $variantId,
            'price' => $price,
        ]);

        try {
            $data = $this->executeGraphQL($shopDomain, $query, $variables);
            
            $result = $data['productVariantsBulkUpdate'] ?? [];
            
            if (!empty($result['userErrors'])) {
                $errorMessages = array_map(function($error) {
                    return ($error['message'] ?? '') . ' (' . ($error['field'] ?? 'unknown') . ')';
                }, $result['userErrors']);
                
                throw new ShopifyApiException('Failed to update variant price: ' . implode(', ', $errorMessages));
            }
            
            if (empty($result['productVariants'])) {
                throw new ShopifyApiException('Variant price update failed: No variant returned');
            }

            Log::info('Variant price updated successfully', [
                'shop' => $shopDomain,
                'variantId' => $variantId,
                'newPrice' => $price,
            ]);

            return $result;
        } catch (ShopifyApiException $e) {
            Log::error('Variant price update failed', [
                'shop' => $shopDomain,
                'variantId' => $variantId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Inventory quantity güncelle (stock)
     * Profesyonel ve matematiksel olarak doğru stock güncelleme
     * Shopify GraphQL Admin API 2024-10 standardına uygun
     * 
     * Strateji:
     * 1. Mevcut stoku kontrol et
     * 2. Location'da aktif değilse aktive et
     * 3. compareQuantity ile mevcut stoku kontrol ederek mutlak değere set et
     * 4. Bu sayede delta eklenmez, direkt istenen değer set edilir
     */
    /**
     * Variant stock güncelle - hem on_hand hem available
     * @param int|null $onHand Eldeki Miktar (on_hand) - null ise güncelleme yapılmaz
     * @param int|null $available Mevcut (available) - null ise güncelleme yapılmaz
     */
    public function updateVariantStock(
        string $shopDomain,
        string $inventoryItemId,
        string $locationId,
        ?int $onHand = null,
        ?int $available = null
    ): array {
        // En az bir değer verilmiş olmalı
        if ($onHand === null && $available === null) {
            throw new ShopifyApiException('Either onHand or available must be provided');
        }
        
        // 1. Önce mevcut stock'ları kontrol et - location'da aktif mi?
        $currentOnHand = $this->getCurrentInventoryQuantity($shopDomain, $inventoryItemId, $locationId, 'on_hand');
        $currentAvailable = $this->getCurrentInventoryQuantity($shopDomain, $inventoryItemId, $locationId, 'available');
        
        // 2. Eğer stock null ise (location'da aktif değil), sadece o zaman aktive et
        if ($currentOnHand === null && $currentAvailable === null) {
            // Activation yap - response'dan stok bilgisini al
            $activationStock = $this->activateInventoryAtLocation($shopDomain, $inventoryItemId, $locationId);
            // Activation response'dan stok al, yoksa query ile al
            $currentOnHand = $activationStock ?? $this->getCurrentInventoryQuantity($shopDomain, $inventoryItemId, $locationId, 'on_hand') ?? 0;
            $currentAvailable = $activationStock ?? $this->getCurrentInventoryQuantity($shopDomain, $inventoryItemId, $locationId, 'available') ?? 0;
            
            Log::info('Inventory activated before stock update', [
                'shop' => $shopDomain,
                'inventoryItemId' => $inventoryItemId,
                'locationId' => $locationId,
                'stockAfterActivation' => ['on_hand' => $currentOnHand, 'available' => $currentAvailable],
            ]);
        }
        
        // 3. Mevcut stock'ları tekrar kontrol et - en güncel değerleri al
        $actualCurrentOnHand = $this->getCurrentInventoryQuantity($shopDomain, $inventoryItemId, $locationId, 'on_hand');
        $actualCurrentAvailable = $this->getCurrentInventoryQuantity($shopDomain, $inventoryItemId, $locationId, 'available');
        if ($actualCurrentOnHand !== null) {
            $currentOnHand = $actualCurrentOnHand;
        }
        if ($actualCurrentAvailable !== null) {
            $currentAvailable = $actualCurrentAvailable;
        }
        
        // 4. MUTLAK DEĞER set etmek için özel strateji:
        // Shopify'da "available" (Mevcut) ve "on_hand" (Eldeki Miktar) ayrı değerler
        // Kullanıcı her ikisini de ayrı ayrı güncellemek istiyor
        // Önce mevcut stoku 0'a çekip, sonra istenen değeri set ediyoruz
        
        $results = [];
        
        // on_hand güncelle (eğer verilmişse)
        if ($onHand !== null) {
            // Önce mevcut on_hand'ı 0'a çek (eğer 0 değilse)
            if ($currentOnHand > 0) {
                $this->setInventoryQuantity($shopDomain, $inventoryItemId, $locationId, 0, 'on_hand');
            }
            // Sonra istenen değeri set et
            $this->setInventoryQuantity($shopDomain, $inventoryItemId, $locationId, $onHand, 'on_hand');
            $results['on_hand'] = $onHand;
        }
        
        // available güncelle (eğer verilmişse)
        if ($available !== null) {
            // Önce mevcut available'ı 0'a çek (eğer 0 değilse)
            if ($currentAvailable > 0) {
                $this->setInventoryQuantity($shopDomain, $inventoryItemId, $locationId, 0, 'available');
            }
            // Sonra istenen değeri set et
            $this->setInventoryQuantity($shopDomain, $inventoryItemId, $locationId, $available, 'available');
            $results['available'] = $available;
        }

        Log::info('Updating variant stock (absolute value)', [
            'shop' => $shopDomain,
            'inventoryItemId' => $inventoryItemId,
            'locationId' => $locationId,
            'requestedOnHand' => $onHand,
            'requestedAvailable' => $available,
            'currentOnHand' => $currentOnHand,
            'currentAvailable' => $currentAvailable,
            'note' => 'Setting on_hand and/or available to ABSOLUTE values (NOT delta, direct values)',
        ]);

        try {
            // Güncelleme başarılı
            Log::info('Variant stock updated successfully', [
                'shop' => $shopDomain,
                'inventoryItemId' => $inventoryItemId,
                'updated' => $results,
            ]);

            return [
                'inventoryAdjustmentGroup' => [
                    'reason' => 'correction',
                    'changes' => array_map(function($name, $value) use ($currentOnHand, $currentAvailable) {
                        $current = $name === 'on_hand' ? $currentOnHand : $currentAvailable;
                        return [
                            'name' => $name,
                            'delta' => $value - ($current ?? 0),
                            'quantityAfterChange' => $value,
                        ];
                    }, array_keys($results), $results),
                ],
                'userErrors' => [],
            ];
        } catch (ShopifyApiException $e) {
            Log::error('Stock update failed', [
                'shop' => $shopDomain,
                'inventoryItemId' => $inventoryItemId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Mevcut inventory quantity'yi al
     * Shopify GraphQL API 2024-10'da inventoryLevel artık id ile çağrılıyor
     * Bu yüzden inventoryItem üzerinden inventoryLevel'ları alıyoruz
     * @param string $name 'available' veya 'on_hand' - default: 'available'
     */
    protected function getCurrentInventoryQuantity(
        string $shopDomain,
        string $inventoryItemId,
        string $locationId,
        string $name = 'available'
    ): ?int {
        $query = <<<'GRAPHQL'
            query GetInventoryLevels($inventoryItemId: ID!) {
              inventoryItem(id: $inventoryItemId) {
                id
                inventoryLevels(first: 10) {
                  edges {
                    node {
                      id
                      location {
                        id
                      }
                      quantities(names: ["available", "on_hand"]) {
                        name
                        quantity
                      }
                    }
                  }
                }
              }
            }
        GRAPHQL;

        $variables = [
            'inventoryItemId' => $inventoryItemId,
        ];

        try {
            $data = $this->executeGraphQL($shopDomain, $query, $variables);
            $inventoryItem = $data['inventoryItem'] ?? null;
            
            // Eğer inventoryItem null ise, item bulunamadı
            if ($inventoryItem === null) {
                return null;
            }
            
            // Location'a göre inventoryLevel'ı bul
            $inventoryLevels = $inventoryItem['inventoryLevels']['edges'] ?? [];
            foreach ($inventoryLevels as $edge) {
                $level = $edge['node'] ?? null;
                if ($level && isset($level['location']['id']) && $level['location']['id'] === $locationId) {
                    // Location eşleşti, quantities'i al
                    if (!empty($level['quantities'])) {
                        // Belirtilen name'e göre quantity'yi bul (available veya on_hand)
                        foreach ($level['quantities'] as $qty) {
                            if ($qty['name'] === $name) {
                                $quantity = (int) ($qty['quantity'] ?? 0);
                                Log::debug('Current inventory quantity retrieved', [
                                    'shop' => $shopDomain,
                                    'inventoryItemId' => $inventoryItemId,
                                    'locationId' => $locationId,
                                    'name' => $name,
                                    'quantity' => $quantity,
                                ]);
                                return $quantity;
                            }
                        }
                    }
                    // Location aktif ama quantities yok - stok 0
                    Log::debug('Inventory level found but no quantities', [
                        'shop' => $shopDomain,
                        'inventoryItemId' => $inventoryItemId,
                        'locationId' => $locationId,
                        'name' => $name,
                    ]);
                    return 0;
                }
            }
            
            Log::debug('No inventory level found for location', [
                'shop' => $shopDomain,
                'inventoryItemId' => $inventoryItemId,
                'locationId' => $locationId,
                'inventoryLevelsCount' => count($inventoryLevels),
            ]);
            
            // Location'da aktif değil
            return null;
        } catch (ShopifyApiException $e) {
            // Eğer "not found" hatası varsa, location'da aktif değil demektir
            $errorMessage = $e->getMessage();
            if (stripos($errorMessage, 'not found') !== false || 
                stripos($errorMessage, 'not stocked') !== false) {
                return null; // Location'da aktif değil
            }
            
            Log::warning('Failed to get current inventory quantity', [
                'shop' => $shopDomain,
                'inventoryItemId' => $inventoryItemId,
                'locationId' => $locationId,
                'error' => $errorMessage,
            ]);
            return null;
        }
    }

    /**
     * Inventory quantity'yi mutlak değer olarak set et
     * @param string $name 'available' veya 'on_hand'
     */
    protected function setInventoryQuantity(
        string $shopDomain,
        string $inventoryItemId,
        string $locationId,
        int $quantity,
        string $name = 'available'
    ): void {
        $query = <<<'GRAPHQL'
            mutation SetInventoryQuantity($input: InventorySetQuantitiesInput!) {
              inventorySetQuantities(input: $input) {
                inventoryAdjustmentGroup {
                  reason
                  changes {
                    name
                    delta
                    quantityAfterChange
                  }
                }
                userErrors {
                  field
                  message
                }
              }
            }
        GRAPHQL;

        $variables = [
            'input' => [
                'reason' => 'correction',
                'name' => $name, // 'available' veya 'on_hand'
                'ignoreCompareQuantity' => true,
                'quantities' => [
                    [
                        'inventoryItemId' => $inventoryItemId,
                        'locationId' => $locationId,
                        'quantity' => $quantity, // Mutlak değer
                    ],
                ],
            ],
        ];

        try {
            $data = $this->executeGraphQL($shopDomain, $query, $variables);
            $result = $data['inventorySetQuantities'] ?? [];
            
            if (!empty($result['userErrors'])) {
                Log::warning("Failed to set {$name} quantity", [
                    'shop' => $shopDomain,
                    'inventoryItemId' => $inventoryItemId,
                    'locationId' => $locationId,
                    'name' => $name,
                    'quantity' => $quantity,
                    'errors' => $result['userErrors'],
                ]);
                throw new ShopifyApiException("Failed to set {$name}: " . json_encode($result['userErrors']));
            } else {
                Log::debug("Inventory quantity set successfully", [
                    'shop' => $shopDomain,
                    'inventoryItemId' => $inventoryItemId,
                    'locationId' => $locationId,
                    'name' => $name,
                    'quantity' => $quantity,
                ]);
            }
        } catch (ShopifyApiException $e) {
            Log::error("Failed to set {$name} quantity", [
                'shop' => $shopDomain,
                'inventoryItemId' => $inventoryItemId,
                'locationId' => $locationId,
                'name' => $name,
                'quantity' => $quantity,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Mevcut stoku 0'a çek (mutlak değer set etmek için)
     * Hem available hem on_hand için 0'a çeker
     */
    protected function setStockToZero(
        string $shopDomain,
        string $inventoryItemId,
        string $locationId,
        int $currentStock
    ): void {
        // Hem on_hand hem available'ı 0'a çek
        try {
            $this->setInventoryQuantity($shopDomain, $inventoryItemId, $locationId, 0, 'on_hand');
        } catch (ShopifyApiException $e) {
            // Hata olsa bile devam et
            Log::debug('Failed to set on_hand to zero (continuing)', [
                'shop' => $shopDomain,
                'inventoryItemId' => $inventoryItemId,
                'error' => $e->getMessage(),
            ]);
        }
        
        try {
            $this->setInventoryQuantity($shopDomain, $inventoryItemId, $locationId, 0, 'available');
        } catch (ShopifyApiException $e) {
            // Hata olsa bile devam et
            Log::debug('Failed to set available to zero (continuing)', [
                'shop' => $shopDomain,
                'inventoryItemId' => $inventoryItemId,
                'error' => $e->getMessage(),
            ]);
        }
        
        Log::debug('Stock set to zero before absolute update', [
            'shop' => $shopDomain,
            'inventoryItemId' => $inventoryItemId,
            'locationId' => $locationId,
            'previousStock' => $currentStock,
        ]);
    }

    /**
     * Inventory item'ı location'a aktive et
     * Stock güncellemesi yapmadan önce gerekli
     * Returns: Mevcut stock miktarını döndürür (activation sonrası)
     */
    protected function activateInventoryAtLocation(
        string $shopDomain,
        string $inventoryItemId,
        string $locationId
    ): ?int {
        $query = <<<'GRAPHQL'
            mutation ActivateInventory($inventoryItemId: ID!, $locationId: ID!) {
              inventoryActivate(inventoryItemId: $inventoryItemId, locationId: $locationId) {
                inventoryLevel {
                  id
                  quantities(names: ["available"]) {
                    name
                    quantity
                  }
                }
                userErrors {
                  field
                  message
                }
              }
            }
        GRAPHQL;

        $variables = [
            'inventoryItemId' => $inventoryItemId,
            'locationId' => $locationId,
        ];

        try {
            $data = $this->executeGraphQL($shopDomain, $query, $variables);
            $result = $data['inventoryActivate'] ?? [];
            
            // Eğer zaten aktive edilmişse userError olabilir, bu normal
            if (!empty($result['userErrors'])) {
                $errorMessage = $result['userErrors'][0]['message'] ?? '';
                // "already activated" veya "already stocked" gibi hatalar normal
                if (stripos($errorMessage, 'already') === false && 
                    stripos($errorMessage, 'activated') === false &&
                    stripos($errorMessage, 'stocked') === false) {
                    Log::warning('Inventory activation warning', [
                        'shop' => $shopDomain,
                        'inventoryItemId' => $inventoryItemId,
                        'locationId' => $locationId,
                        'errors' => $result['userErrors'],
                    ]);
                } else {
                    // Zaten aktive edilmiş, bu normal - mevcut stock'u al
                    $currentStock = $this->getCurrentInventoryQuantity($shopDomain, $inventoryItemId, $locationId);
                    Log::debug('Inventory already activated at location', [
                        'shop' => $shopDomain,
                        'inventoryItemId' => $inventoryItemId,
                        'locationId' => $locationId,
                        'currentStock' => $currentStock,
                    ]);
                    return $currentStock;
                }
            } else {
                // Başarılı activation - mevcut stock'u döndür
                $inventoryLevel = $result['inventoryLevel'] ?? null;
                $currentStock = 0;
                
                if ($inventoryLevel && !empty($inventoryLevel['quantities'])) {
                    $currentStock = (int) ($inventoryLevel['quantities'][0]['quantity'] ?? 0);
                }
                
                Log::info('Inventory activated at location', [
                    'shop' => $shopDomain,
                    'inventoryItemId' => $inventoryItemId,
                    'locationId' => $locationId,
                    'inventoryLevel' => $inventoryLevel,
                    'currentStock' => $currentStock,
                ]);
                
                return $currentStock;
            }
        } catch (ShopifyApiException $e) {
            // Activation hatası stock update'i engellemesin, sadece log'la
            // Belki zaten aktive edilmiştir ve stock update çalışacaktır
            Log::warning('Failed to activate inventory at location (will try stock update anyway)', [
                'shop' => $shopDomain,
                'inventoryItemId' => $inventoryItemId,
                'locationId' => $locationId,
                'error' => $e->getMessage(),
            ]);
            // Devam et, belki zaten aktive edilmiştir - mevcut stock'u query ile al
            return $this->getCurrentInventoryQuantity($shopDomain, $inventoryItemId, $locationId);
        }
        
        return null;
    }

    /**
     * Shop'un location ID'sini al
     */
    /**
     * Tüm location'ları getir (name ve id ile)
     */
    public function getAllLocations(string $shopDomain): array
    {
        $query = <<<'GRAPHQL'
            query GetLocations {
              locations(first: 250) {
                edges {
                  node {
                    id
                    name
                  }
                }
              }
            }
        GRAPHQL;

        try {
            $data = $this->executeGraphQL($shopDomain, $query, []);
            $locations = $data['locations']['edges'] ?? [];
            
            $result = [];
            foreach ($locations as $edge) {
                $location = $edge['node'] ?? null;
                if ($location) {
                    $result[] = [
                        'id' => $location['id'],
                        'name' => $location['name'] ?? 'Unknown Location',
                    ];
                }
            }
            
            Log::debug('Locations fetched', [
                'shop' => $shopDomain,
                'count' => count($result),
            ]);
            
            return $result;
        } catch (ShopifyApiException $e) {
            Log::error('Failed to fetch locations', [
                'shop' => $shopDomain,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * İlk location ID'yi getir (backward compatibility için)
     */
    public function getShopLocationId(string $shopDomain): ?string
    {
        $locations = $this->getAllLocations($shopDomain);
        
        if (!empty($locations)) {
            return $locations[0]['id'] ?? null;
        }
        
        return null;
    }
}
