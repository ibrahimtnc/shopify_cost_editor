<?php

namespace App\Services\Shopify;

use App\Models\Shop;
use App\Models\CostAuditLog;
use App\Exceptions\ShopifyApiException;
use Illuminate\Support\Facades\Log;

class InventoryService extends ShopifyService
{
    /**
     * Inventory item cost güncelle
     * Shopify GraphQL Admin API 2024-10 resmi dokümantasyonuna uygun
     * 
     * @param string $shopDomain
     * @param string $inventoryItemId GID formatında olmalı: gid://shopify/InventoryItem/123456789
     * @param float $costAmount
     * @param string $currencyCode
     * @param float|null $oldCost Audit log için
     * @return array
     * @throws ShopifyApiException
     */
    public function updateInventoryItemCost(
        string $shopDomain,
        string $inventoryItemId,
        float $costAmount,
        string $currencyCode = 'USD',
        ?float $oldCost = null,
        ?string $productId = null,
        ?string $variantId = null
    ): array {
        // Shopify resmi dokümantasyonuna göre mutation
        $query = <<<'GRAPHQL'
            mutation UpdateInventoryItemCost($id: ID!, $input: InventoryItemInput!) {
              inventoryItemUpdate(id: $id, input: $input) {
                inventoryItem {
                  id
                  unitCost {
                    amount
                    currencyCode
                  }
                  tracked
                }
                userErrors {
                  field
                  message
                }
              }
            }
        GRAPHQL;

        // Shopify'ın beklediği format: cost direkt olarak decimal (string) olmalı
        // MoneyInput objesi değil, direkt sayısal değer
        $variables = [
            'id' => $inventoryItemId,
            'input' => [
                'cost' => number_format($costAmount, 2, '.', ''), // Direkt string olarak decimal
            ],
        ];

        Log::info('Updating inventory item cost', [
            'shop' => $shopDomain,
            'inventoryItemId' => $inventoryItemId,
            'costAmount' => $costAmount,
            'currencyCode' => $currencyCode,
        ]);

        try {
            $data = $this->executeGraphQL($shopDomain, $query, $variables);
            
            $result = $data['inventoryItemUpdate'] ?? [];
            
            // User errors kontrolü
            if (!empty($result['userErrors'])) {
                $errorMessages = array_map(function($error) {
                    return ($error['message'] ?? '') . ' (' . ($error['field'] ?? 'unknown') . ')';
                }, $result['userErrors']);
                
                $errorMessage = 'Failed to update inventory cost: ' . implode(', ', $errorMessages);
                
                Log::error('Inventory update user errors', [
                    'shop' => $shopDomain,
                    'inventoryItemId' => $inventoryItemId,
                    'errors' => $result['userErrors'],
                ]);
                
                throw new ShopifyApiException($errorMessage);
            }
            
            // Response validation
            if (empty($result['inventoryItem'])) {
                Log::error('Inventory update returned no item', [
                    'shop' => $shopDomain,
                    'inventoryItemId' => $inventoryItemId,
                    'response' => $result,
                ]);
                throw new ShopifyApiException('Inventory item update failed: No item returned');
            }

            // Response'dan dönen değeri logla
            $updatedUnitCost = $result['inventoryItem']['unitCost'] ?? null;
            Log::info('Inventory item cost updated successfully', [
                'shop' => $shopDomain,
                'inventoryItemId' => $inventoryItemId,
                'newCost' => $costAmount,
                'responseUnitCost' => $updatedUnitCost,
                'fullResponse' => $result['inventoryItem'],
            ]);

            // Audit log
            $this->logCostUpdate($shopDomain, $inventoryItemId, $oldCost, $costAmount, $currencyCode, $productId, $variantId);

            return $result;
        } catch (ShopifyApiException $e) {
            Log::error('Inventory update failed', [
                'shop' => $shopDomain,
                'inventoryItemId' => $inventoryItemId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Toplu cost update
     * Her bir item için ayrı mutation çağırır (daha güvenilir)
     */
    public function bulkUpdateInventoryCosts(string $shopDomain, array $updates): array
    {
        $results = [];
        $errors = [];
        
        foreach ($updates as $update) {
            try {
                $result = $this->updateInventoryItemCost(
                    $shopDomain,
                    $update['inventoryItemId'],
                    (float) $update['cost'],
                    $update['currencyCode'] ?? 'USD',
                    isset($update['oldCost']) ? (float) $update['oldCost'] : null,
                    $update['productId'] ?? null,
                    $update['variantId'] ?? null
                );
                $results[] = $result;
                
                // Rate limit için kısa bir bekleme
                usleep(100000); // 100ms
            } catch (ShopifyApiException $e) {
                $errors[] = [
                    'inventoryItemId' => $update['inventoryItemId'],
                    'error' => $e->getMessage(),
                ];
            }
        }
        
        if (!empty($errors)) {
            throw new ShopifyApiException(
                'Bulk update completed with errors: ' . json_encode($errors) . 
                '. Successfully updated: ' . count($results) . ' items.'
            );
        }
        
        return [
            'inventoryItems' => $results,
            'userErrors' => [],
        ];
    }

    /**
     * Cost update audit log
     */
    protected function logCostUpdate(
        string $shopDomain,
        string $inventoryItemId,
        ?float $oldCost,
        float $newCost,
        string $currencyCode = 'USD',
        ?string $productId = null,
        ?string $variantId = null
    ): void {
        $this->logAuditChange(
            $shopDomain,
            CostAuditLog::FIELD_TYPE_COST,
            'cost',
            $inventoryItemId,
            $oldCost,
            $newCost,
            $currencyCode,
            $productId,
            $variantId
        );
    }

    /**
     * Generic audit log method for all field changes (cost, price, stock)
     */
    public function logAuditChange(
        string $shopDomain,
        string $fieldType,
        string $fieldName,
        string $inventoryItemId,
        ?float $oldValue,
        ?float $newValue,
        string $currencyCode = 'USD',
        ?string $productId = null,
        ?string $variantId = null
    ): void {
        try {
            $shop = Shop::where('shop_domain', $shopDomain)->first();
            
            if (!$shop) {
                Log::warning('Shop not found for audit log', ['shop' => $shopDomain]);
                return;
            }

            CostAuditLog::create([
                'shop_id' => $shop->id,
                'product_id' => $productId,
                'variant_id' => $variantId,
                'inventory_item_id' => $inventoryItemId,
                'field_type' => $fieldType,
                'field_name' => $fieldName,
                'old_value' => $oldValue,
                'new_value' => $newValue,
                'currency_code' => $currencyCode,
                // Specific fields for each type
                'old_cost' => $fieldType === CostAuditLog::FIELD_TYPE_COST ? $oldValue : null,
                'new_cost' => $fieldType === CostAuditLog::FIELD_TYPE_COST ? $newValue : null,
                'old_price' => $fieldType === CostAuditLog::FIELD_TYPE_PRICE ? $oldValue : null,
                'new_price' => $fieldType === CostAuditLog::FIELD_TYPE_PRICE ? $newValue : null,
                'old_stock' => $fieldType === CostAuditLog::FIELD_TYPE_STOCK ? $oldValue : null,
                'new_stock' => $fieldType === CostAuditLog::FIELD_TYPE_STOCK ? $newValue : null,
            ]);
        } catch (\Exception $e) {
            // Audit log hatası uygulamayı durdurmamalı
            Log::warning('Failed to log audit change', [
                'error' => $e->getMessage(),
                'field_type' => $fieldType,
                'inventory_item_id' => $inventoryItemId,
            ]);
        }
    }
}
