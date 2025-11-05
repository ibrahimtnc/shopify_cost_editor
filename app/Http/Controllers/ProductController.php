<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\Shopify\ProductService;
use App\Exceptions\ShopifyApiException;
use Illuminate\Support\Facades\Log;

class ProductController extends Controller
{
    protected ProductService $productService;

    public function __construct(ProductService $productService)
    {
        $this->productService = $productService;
    }

    /**
     * Ürün listesi
     */
    public function index(Request $request)
    {
        $shopDomain = session('shop_domain');
        
        if (!$shopDomain) {
            return redirect('/shopify/install');
        }

        try {
            $after = $request->get('after');
            $result = $this->productService->getProducts($shopDomain, 20, $after);
            
            $products = $result['products'];
            $pageInfo = $result['pageInfo'];

            return view('products.index', [
                'products' => $products,
                'pageInfo' => $pageInfo,
                'shopDomain' => $shopDomain,
            ]);
        } catch (ShopifyApiException $e) {
            Log::error('Product list error', [
                'error' => $e->getMessage(),
                'shop' => $shopDomain,
            ]);
            return view('products.index', [
                'products' => [],
                'pageInfo' => ['hasNextPage' => false, 'endCursor' => null],
                'shopDomain' => $shopDomain,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Ürün düzenleme sayfası
     */
    public function edit(Request $request, string $productId)
    {
        $shopDomain = session('shop_domain');
        
        if (!$shopDomain) {
            return redirect('/shopify/install');
        }

        // URL decode product ID
        $productId = urldecode($productId);

        try {
            // Tüm location'ları al
            $locations = $this->productService->getAllLocations($shopDomain);
            
            // Default location ID (ilk location veya request'ten gelen)
            $defaultLocationId = $request->get('location_id') ?? ($locations[0]['id'] ?? null);
            
            // Product'ı location ID ile birlikte getir (inventoryLevel bilgileri dahil)
            $product = $this->productService->getProductWithVariants($shopDomain, $productId, $defaultLocationId);
            
            if (!$product) {
                return redirect('/products')->with('error', 'Product not found');
            }

            // Debug: Variant bilgilerini logla
            $variants = $product['variants']['edges'] ?? [];
            
            // Debug: İlk variant'ın inventoryOnHand değerini kontrol et
            $firstVariant = $variants[0]['node'] ?? null;
            Log::debug('Product variants loaded', [
                'shop' => $shopDomain,
                'productId' => $productId,
                'variantsCount' => count($variants),
                'selectedLocationId' => $defaultLocationId,
                'firstVariantHasInventoryOnHand' => isset($firstVariant['inventoryOnHand']),
                'firstVariantInventoryOnHand' => $firstVariant['inventoryOnHand'] ?? 'NOT SET',
                'firstVariantInventoryAvailable' => $firstVariant['inventoryAvailable'] ?? 'NOT SET',
                'firstVariantTotalOnHand' => $firstVariant['totalInventoryOnHand'] ?? 'NOT SET',
                'firstVariantTotalAvailable' => $firstVariant['totalInventoryAvailable'] ?? 'NOT SET',
            ]);

            return view('products.edit', [
                'product' => $product,
                'variants' => $variants,
                'shopDomain' => $shopDomain,
                'locations' => $locations,
                'selectedLocationId' => $defaultLocationId,
            ]);
        } catch (ShopifyApiException $e) {
            Log::error('Product edit error', [
                'error' => $e->getMessage(),
                'productId' => $productId,
                'shop' => $shopDomain,
            ]);
            return redirect('/products')->with('error', 'Failed to load product: ' . $e->getMessage());
        }
    }
}
