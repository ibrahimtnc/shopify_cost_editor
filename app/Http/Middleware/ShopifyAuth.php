<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Shop;
use Symfony\Component\HttpFoundation\Response;

class ShopifyAuth
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Sadece session'dan shop domain'i kontrol et
        // Otomatik shop domain alma mekanizması güvenlik açığı oluşturur
        $shopDomain = session('shop_domain');
        
        // AJAX/JSON request kontrolü
        $wantsJson = $request->wantsJson() || $request->expectsJson() || $request->isJson();
        
        // Eğer session'da shop domain yoksa, kullanıcı authenticate olmamış demektir
        if (!$shopDomain) {
            // AJAX request ise JSON döndür
            if ($wantsJson) {
                return response()->json([
                    'success' => false,
                    'message' => 'Shop not authenticated. Please login.',
                    'redirect' => '/shopify/install'
                ], 401);
            }
            return redirect('/shopify/install')->with('error', 'Please authenticate to access this page.');
        }

        // Shop kaydını kontrol et
        $shop = Shop::where('shop_domain', $shopDomain)->first();
        
        // Shop kaydı yoksa veya access_token yoksa
        if (!$shop || !$shop->access_token) {
            // Session'ı temizle - geçersiz shop kaydı
            $request->session()->forget('shop_domain');
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            
            // AJAX request ise JSON döndür
            if ($wantsJson) {
                return response()->json([
                    'success' => false,
                    'message' => 'Shop not authenticated or token expired. Please re-authenticate.',
                    'redirect' => '/shopify/install?shop=' . urlencode($shopDomain)
                ], 401);
            }
            return redirect('/shopify/install?shop=' . urlencode($shopDomain))
                ->with('error', 'Your session has expired. Please re-authenticate.');
        }

        return $next($request);
    }
}

