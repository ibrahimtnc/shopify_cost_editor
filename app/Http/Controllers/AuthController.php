<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\Shopify\AuthService;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    protected AuthService $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    /**
     * Shopify OAuth installation başlat
     */
    public function install(Request $request)
    {
        // Logout sonrası geldiyse session'ı temizle
        if ($request->has('logout')) {
            $request->session()->flush();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }
        
        $shopDomain = $request->get('shop');
        
        if (!$shopDomain) {
            return view('auth.install');
        }

        // Shop domain formatını düzelt (myshopify.com ekle)
        if (!str_contains($shopDomain, '.myshopify.com')) {
            $shopDomain = $shopDomain . '.myshopify.com';
        }

        try {
            $installationUrl = $this->authService->getInstallationUrl($shopDomain);
            
            // Embedded app içinde miyiz kontrol et
            $referer = $request->header('Referer', '');
            $isEmbedded = preg_match('/admin\.shopify\.com/', $referer) || 
                         preg_match('/\.myshopify\.(com|io)/', $referer) ||
                         session('shop_domain');
            
            // Embedded app içindeyse, OAuth'u yeni sekmede aç
            if ($isEmbedded) {
                return response()->view('auth.oauth-popup', [
                    'installationUrl' => $installationUrl
                ]);
            }
            
            // Normal redirect (domain'den giriş)
            return redirect($installationUrl);
        } catch (\Exception $e) {
            Log::error('Installation error', ['error' => $e->getMessage()]);
            return back()->with('error', 'Failed to start installation: ' . $e->getMessage());
        }
    }

    /**
     * Shopify OAuth callback
     */
    public function callback(Request $request)
    {
        $shopDomain = $request->get('shop');
        $code = $request->get('code');
        $state = $request->get('state');
        $hmac = $request->get('hmac');

        // HMAC verification
        if ($hmac) {
            $params = $request->all();
            if (!$this->authService->verifyHmac($params, $hmac)) {
                abort(403, 'Invalid HMAC signature');
            }
        }

        if (!$shopDomain || !$code || !$state) {
            return redirect('/shopify/install')->with('error', 'Missing required parameters');
        }

        // Shop domain formatını düzelt
        if (!str_contains($shopDomain, '.myshopify.com')) {
            $shopDomain = $shopDomain . '.myshopify.com';
        }

        try {
            $shop = $this->authService->handleCallback($shopDomain, $code, $state);
            
            // Session'a shop domain'i kaydet
            session(['shop_domain' => $shopDomain]);
            
            // Embedded app için: Shopify embedded app URL'ini oluştur
            $apiKey = config('shopify.api_key');
            $embeddedUrl = "https://{$shopDomain}/admin/apps/{$apiKey}";
            
            // Callback'i intermediate sayfaya yönlendir (popup kontrolü için)
            // Bu sayfa popup içindeyse parent frame'i güncelleyecek, değilse embedded app URL'ine yönlendirecek
            return response()->view('auth.oauth-callback', [
                'embeddedUrl' => $embeddedUrl,
                'shopDomain' => $shopDomain
            ]);
        } catch (\Exception $e) {
            Log::error('OAuth callback error', [
                'error' => $e->getMessage(),
                'shop' => $shopDomain,
            ]);
            return redirect('/shopify/install')->with('error', 'Authentication failed: ' . $e->getMessage());
        }
    }

    /**
     * Logout - Session'ı tamamen temizle ve kullanıcıyı logout sayfasına yönlendir
     */
    public function logout(Request $request)
    {
        $shopDomain = session('shop_domain');
        
        // AJAX/JSON request kontrolü
        $wantsJson = $request->wantsJson() || $request->expectsJson() || $request->isJson();
        
        // Tüm session verilerini temizle
        $request->session()->flush();
        
        // Session'ı geçersiz kıl ve yeni token oluştur
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        
        Log::info('User logged out', ['shop' => $shopDomain]);
        
        // AJAX request ise JSON döndür
        if ($wantsJson) {
            return response()->json([
                'success' => true,
                'message' => 'Successfully logged out',
                'redirect' => '/shopify/install'
            ]);
        }
        
        // Shopify install sayfasına yönlendir ve query string ile cache bypass
        return redirect('/shopify/install?logout=' . time())
            ->with('success', 'Successfully logged out. Please login again to continue.');
    }
}
