<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\UpdateCostRequest;
use App\Services\Shopify\InventoryService;
use App\Services\Shopify\ProductService;
use App\Exceptions\ShopifyApiException;
use App\Models\CostAuditLog;
use Illuminate\Support\Facades\Log;

class CostController extends Controller
{
    protected InventoryService $inventoryService;
    protected ProductService $productService;

    public function __construct(
        InventoryService $inventoryService,
        ProductService $productService
    ) {
        $this->inventoryService = $inventoryService;
        $this->productService = $productService;
    }

    /**
     * Cost, Price ve Stock güncelle
     * İş case gereksinimlerine uygun: Cost güncelleme + Bonus: Price ve Stock
     */
    public function update(UpdateCostRequest $request, string $productId)
    {
        $shopDomain = session('shop_domain');
        
        if (!$shopDomain) {
            return response()->json([
                'success' => false,
                'message' => 'Shop not authenticated',
            ], 401);
        }

        // URL decode product ID
        $productId = urldecode($productId);

        $costs = $request->input('costs', []);
        $currencyCode = $request->input('currencyCode', 'USD');
        
        // Eğer hiç değişiklik yoksa hata döndür
        if (empty($costs)) {
            return response()->json([
                'success' => false,
                'message' => 'No changes to update',
            ], 400);
        }
        
            // Location ID'yi request'ten al veya default location
            $locationId = $request->input('locationId') ?? $this->productService->getShopLocationId($shopDomain);
            
            Log::debug('Cost update location check', [
                'shop' => $shopDomain,
                'locationIdFromRequest' => $request->input('locationId'),
                'locationIdUsed' => $locationId,
                'costsData' => array_map(function($cost) {
                    return [
                        'inventoryItemId' => $cost['inventoryItemId'] ?? null,
                        'locationId' => $cost['locationId'] ?? null,
                        'onHand' => $cost['onHand'] ?? null,
                        'available' => $cost['available'] ?? null,
                    ];
                }, $costs),
            ]);

        try {
            $updatedCosts = 0;
            $updatedPrices = 0;
            $updatedStocks = 0;
            $errors = [];

            foreach ($costs as $data) {
                $variantErrors = [];

                // 1. Cost güncelle - sadece değişen değerleri
                if (isset($data['cost']) && $data['cost'] !== null && $data['cost'] !== '') {
                    $newCost = (float) $data['cost'];
                    $oldCost = isset($data['oldCost']) ? (float) $data['oldCost'] : null;
                    
                    // Sadece değer değiştiyse güncelle
                    if ($oldCost === null || $newCost !== $oldCost) {
                    try {
                            $updateProductId = $data['productId'] ?? $productId;
                            
                        $this->inventoryService->updateInventoryItemCost(
                            $shopDomain,
                            $data['inventoryItemId'],
                                $newCost,
                            $data['currencyCode'] ?? $currencyCode,
                                $oldCost,
                                $updateProductId,
                                $data['variantId'] ?? null
                        );
                        $updatedCosts++;
                    } catch (ShopifyApiException $e) {
                        $variantErrors[] = 'Cost: ' . $e->getMessage();
                        }
                    }
                }

                // 2. Price güncelle - sadece değişen değerleri
                if (isset($data['price']) && $data['price'] !== null && $data['price'] !== '' && isset($data['variantId'])) {
                    $newPrice = (float) $data['price'];
                    $oldPrice = isset($data['oldPrice']) ? (float) $data['oldPrice'] : null;
                    
                    // Sadece değer değiştiyse güncelle
                    if ($oldPrice === null || $newPrice !== $oldPrice) {
                    try {
                        $updateProductId = $data['productId'] ?? $productId;
                            
                        $this->productService->updateVariantPrice(
                            $shopDomain,
                            $updateProductId,
                            $data['variantId'],
                                number_format($newPrice, 2, '.', '')
                        );
                            
                            // Audit log for price update
                            $this->inventoryService->logAuditChange(
                                $shopDomain,
                                CostAuditLog::FIELD_TYPE_PRICE,
                                'price',
                                $data['inventoryItemId'],
                                $oldPrice,
                                $newPrice,
                                $data['currencyCode'] ?? $currencyCode,
                                $updateProductId,
                                $data['variantId']
                            );
                            
                        $updatedPrices++;
                    } catch (ShopifyApiException $e) {
                        $variantErrors[] = 'Price: ' . $e->getMessage();
                        }
                    }
                }

                // 3. Stock güncelle - sadece değişen değerleri
                $variantLocationId = $data['locationId'] ?? $locationId;
                
                if ($variantLocationId && isset($data['onHand']) && $data['onHand'] !== null && $data['onHand'] !== '') {
                        $newOnHand = (int) $data['onHand'];
                        $oldOnHand = isset($data['oldOnHand']) ? (int) $data['oldOnHand'] : null;
                        
                    // Sadece değer değiştiyse güncelle
                        if ($oldOnHand === null || $newOnHand !== $oldOnHand) {
                            try {
                                Log::info('Updating variant stock (on_hand only)', [
                                    'shop' => $shopDomain,
                                    'inventoryItemId' => $data['inventoryItemId'],
                                    'locationId' => $variantLocationId,
                                    'onHand' => $newOnHand,
                                'oldOnHand' => $oldOnHand,
                                ]);
                                
                                $this->productService->updateVariantStock(
                                    $shopDomain,
                                    $data['inventoryItemId'],
                                    $variantLocationId,
                                    $newOnHand,
                                    null // available artık güncellenmiyor
                                );
                            
                            // Audit log for stock update
                            $this->inventoryService->logAuditChange(
                                $shopDomain,
                                CostAuditLog::FIELD_TYPE_STOCK,
                                'stock_on_hand',
                                $data['inventoryItemId'],
                                $oldOnHand !== null ? (float) $oldOnHand : null,
                                (float) $newOnHand,
                                $data['currencyCode'] ?? $currencyCode,
                                $data['productId'] ?? $productId,
                                $data['variantId'] ?? null
                            );
                            
                                $updatedStocks++;
                            } catch (ShopifyApiException $e) {
                                Log::error('Stock update failed', [
                                    'shop' => $shopDomain,
                                    'inventoryItemId' => $data['inventoryItemId'],
                                    'locationId' => $variantLocationId,
                                    'error' => $e->getMessage(),
                                ]);
                                $variantErrors[] = 'Stock: ' . $e->getMessage();
                        }
                    }
                }

                if (!empty($variantErrors)) {
                    $errors[] = [
                        'inventoryItemId' => $data['inventoryItemId'],
                        'errors' => $variantErrors,
                    ];
                }
            }

                // Success message oluştur
                $messages = [];
                if ($updatedCosts > 0) {
                    $messages[] = "{$updatedCosts} cost(s)";
                }
                if ($updatedPrices > 0) {
                    $messages[] = "{$updatedPrices} price(s)";
                }
                if ($updatedStocks > 0) {
                    $messages[] = "{$updatedStocks} inventory level(s)";
                }

            if (empty($errors)) {
                return response()->json([
                    'success' => true,
                    'message' => 'Successfully updated: ' . implode(', ', $messages),
                    'updated' => [
                        'costs' => $updatedCosts,
                        'prices' => $updatedPrices,
                        'stocks' => $updatedStocks,
                    ],
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Partially updated: ' . implode(', ', $messages) . '. ' . count($errors) . ' variant(s) failed',
                    'updated' => [
                        'costs' => $updatedCosts,
                        'prices' => $updatedPrices,
                        'stocks' => $updatedStocks,
                    ],
                    'errors' => $errors,
                ], 422);
            }
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
            
            // Array to string conversion hatasını önle
            if (is_array($errorMessage)) {
                $errorMessage = json_encode($errorMessage);
            }
            
            Log::error('Update error', [
                'error' => $errorMessage,
                'exception' => get_class($e),
                'trace' => $e->getTraceAsString(),
                'productId' => $productId,
                'shop' => $shopDomain,
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update: ' . $errorMessage,
            ], 500);
        }
    }
}
