<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CostAuditLog;
use App\Models\Shop;
use App\Services\Shopify\ProductService;
use Illuminate\Support\Facades\Log;

class LogController extends Controller
{
    protected ProductService $productService;

    public function __construct(ProductService $productService)
    {
        $this->productService = $productService;
    }

    /**
     * Audit log listesi
     * Tüm değişiklikleri gösterir: cost, price, stock
     */
    public function index(Request $request)
    {
        $shopDomain = session('shop_domain');
        
        if (!$shopDomain) {
            return redirect('/shopify/install');
        }

        try {
            $shop = Shop::where('shop_domain', $shopDomain)->first();
            
            if (!$shop) {
                return redirect('/shopify/install')->with('error', 'Shop not found');
            }

            // Filtreleme parametreleri
            $fieldType = $request->get('field_type');
            $fieldName = $request->get('field_name');
            $productId = $request->get('product_id');
            $variantId = $request->get('variant_id');
            $perPage = $request->get('per_page', 50);

            // Query builder
            $query = CostAuditLog::where('shop_id', $shop->id)
                ->orderBy('created_at', 'desc');

            // Filtreler
            if ($fieldType) {
                $query->where('field_type', $fieldType);
            }

            if ($fieldName) {
                $query->where('field_name', $fieldName);
            }

            if ($productId) {
                $query->where('product_id', $productId);
            }

            if ($variantId) {
                $query->where('variant_id', $variantId);
            }

            // Pagination
            $logs = $query->paginate($perPage);

            // Product ve variant isimlerini al
            $productNames = [];
            $variantNames = [];
            
            if ($logs->count() > 0) {
                // Unique product ID'leri topla
                $productIds = $logs->pluck('product_id')->filter()->unique()->values()->toArray();
                $variantIds = $logs->pluck('variant_id')->filter()->unique()->values()->toArray();
                
                // Her product için isim al
                foreach ($productIds as $pid) {
                    try {
                        $product = $this->productService->getProductWithVariants($shopDomain, $pid, null);
                        if ($product && isset($product['title'])) {
                            $productNames[$pid] = $product['title'];
                            
                            // Variant isimlerini de al
                            if (isset($product['variants']['edges'])) {
                                foreach ($product['variants']['edges'] as $variantEdge) {
                                    $variant = $variantEdge['node'] ?? null;
                                    if ($variant && isset($variant['id'])) {
                                        $variantTitle = $variant['title'] ?? ($variant['sku'] ?? 'N/A');
                                        $variantNames[$variant['id']] = $variantTitle;
                                    }
                                }
                            }
                        }
                    } catch (\Exception $e) {
                        Log::warning('Failed to fetch product name', [
                            'productId' => $pid,
                            'error' => $e->getMessage(),
                        ]);
                        $productNames[$pid] = null;
                    }
                }
            }

            // Field type ve name seçenekleri
            $fieldTypes = CostAuditLog::select('field_type')
                ->where('shop_id', $shop->id)
                ->distinct()
                ->pluck('field_type')
                ->filter()
                ->values();

            $fieldNames = CostAuditLog::select('field_name')
                ->where('shop_id', $shop->id)
                ->distinct()
                ->pluck('field_name')
                ->filter()
                ->values();

            return view('logs.index', [
                'logs' => $logs,
                'shopDomain' => $shopDomain,
                'fieldTypes' => $fieldTypes,
                'fieldNames' => $fieldNames,
                'productNames' => $productNames,
                'variantNames' => $variantNames,
                'filters' => [
                    'field_type' => $fieldType,
                    'field_name' => $fieldName,
                    'product_id' => $productId,
                    'variant_id' => $variantId,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to load audit logs', [
                'error' => $e->getMessage(),
                'shop' => $shopDomain,
            ]);
            return redirect('/products')->with('error', 'Failed to load audit logs: ' . $e->getMessage());
        }
    }
}
