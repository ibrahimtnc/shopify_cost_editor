<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Cookie;

class ShopifyEmbeddedHeaders
{
    /**
     * Handle an incoming request.
     * 
     * This middleware sets the necessary headers to allow the app
     * to be embedded in Shopify admin panel iframe.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if request is coming from Shopify (embedded app)
        // Session varsa veya referer Shopify'dan geliyorsa header'ları ayarla
        $referer = $request->header('Referer', '');
        $origin = $request->header('Origin', '');
        $isShopifyRequest = $this->isShopifyRequest($referer, $request, $origin);
        $isShopifyApp = $isShopifyRequest || session('shop_domain');
        
        // Shopify request ise session config'i dinamik olarak ayarla
        if ($isShopifyApp) {
            $this->configureSessionForIframe($request);
        }
        
        $response = $next($request);

        // Eğer Shopify request'i ise veya session varsa (embedded app içinde çalışıyor demektir)
        if ($isShopifyApp) {
            // X-Frame-Options'ı kesinlikle kaldır - tüm varyasyonlarını
            // Response gönderilmeden önce her durumda kaldır
            $this->removeXFrameOptions($response);
            
            // Get app URL for CSP
            $appUrl = config('app.url', '');
            $appDomain = parse_url($appUrl, PHP_URL_HOST);
            
            // Set Content-Security-Policy to allow Shopify domains
            $csp = "default-src 'self' " . ($appDomain ? "https://{$appDomain} " : "") . "https://*.myshopify.com https://admin.shopify.com https://cdn.shopify.com https://cdn.tailwindcss.com https://cdn.jsdelivr.net data:; " .
                   "frame-ancestors https://*.myshopify.com https://admin.shopify.com https://*.myshopify.io; " .
                   "script-src 'self' 'unsafe-inline' 'unsafe-eval' " . ($appDomain ? "https://{$appDomain} " : "") . "https://cdn.shopify.com https://*.myshopify.com https://cdn.jsdelivr.net https://cdn.tailwindcss.com; " .
                   "style-src 'self' 'unsafe-inline' " . ($appDomain ? "https://{$appDomain} " : "") . "https://cdn.shopify.com https://cdn.tailwindcss.com https://cdn.jsdelivr.net; " .
                   "connect-src 'self' " . ($appDomain ? "https://{$appDomain} " : "") . "https://*.myshopify.com https://admin.shopify.com https://cdn.shopify.com https://cdn.tailwindcss.com; " .
                   "form-action 'self' " . ($appDomain ? "https://{$appDomain} " : "") . "https://*.myshopify.com https://admin.shopify.com; " .
                   "img-src 'self' data: https: blob:; " .
                   "font-src 'self' data: https:; " .
                   "base-uri 'self';";
            
            $response->headers->set('Content-Security-Policy', $csp);
            $response->headers->set('X-Content-Type-Options', 'nosniff');
            
            // Cookie'leri SameSite=None ve Secure ile ayarla
            $this->modifyCookiesForIframe($response);
            
            // X-Frame-Options'ı tekrar kaldır (başka middleware eklemiş olabilir)
            $this->removeXFrameOptions($response);
        }
        
        // Redirect response'larında da header'ları koru
        if ($response->isRedirection() && $isShopifyApp) {
            $this->removeXFrameOptions($response);
            $csp = "frame-ancestors https://*.myshopify.com https://admin.shopify.com https://*.myshopify.io;";
            $response->headers->set('Content-Security-Policy', $csp);
        }
        
        // Son kontrol: X-Frame-Options kesinlikle olmamalı
        $this->removeXFrameOptions($response);

        return $response;
    }

    /**
     * Check if the request is from Shopify
     */
    private function isShopifyRequest(string $referer, Request $request, string $origin = ''): bool
    {
        // Check referer header (GET request'lerde genelde var)
        if (!empty($referer)) {
            if (preg_match('/\.myshopify\.(com|io)/', $referer) || 
                preg_match('/admin\.shopify\.com/', $referer) ||
                preg_match('/accounts\.shopify\.com/', $referer)) {
                return true;
            }
        }

        // Check origin header (POST request'lerde genelde var)
        if (!empty($origin)) {
            if (preg_match('/\.myshopify\.(com|io)/', $origin) || 
                preg_match('/admin\.shopify\.com/', $origin) ||
                preg_match('/accounts\.shopify\.com/', $origin)) {
                return true;
            }
        }

        // Check if shop parameter exists (Shopify OAuth requests)
        if ($request->has('shop') || $request->has('hmac')) {
            return true;
        }

        // Check if session has shop_domain (authenticated Shopify requests)
        // Bu durumda embedded app içinde çalışıyor demektir
        if (session('shop_domain')) {
            return true;
        }

        // Check if user agent contains Shopify
        $userAgent = $request->header('User-Agent', '');
        if (stripos($userAgent, 'shopify') !== false) {
            return true;
        }

        // Check X-Shopify-* headers (Shopify embedded app'ler bunları gönderir)
        if ($request->hasHeader('X-Shopify-Shop-Domain') || 
            $request->hasHeader('X-Shopify-Topic')) {
            return true;
        }

        return false;
    }
    
    /**
     * Configure session for iframe embedding
     */
    private function configureSessionForIframe(Request $request): void
    {
        // Session config'i dinamik olarak güncelle
        // Bu, session middleware'in cookie'leri doğru ayarlarla oluşturmasını sağlar
        config([
            'session.same_site' => 'none',
            'session.secure' => true,
        ]);
    }
    
    /**
     * Remove X-Frame-Options header from response
     * Tüm olası varyasyonları ve header bag'deki tüm entry'leri kaldırır
     */
    private function removeXFrameOptions(Response $response): void
    {
        // Tüm olası header isimlerini kaldır (case-insensitive)
        $variations = ['X-Frame-Options', 'x-frame-options', 'X-FRAME-OPTIONS', 'X-Frame-Options:', 'x-frame-options:'];
        foreach ($variations as $header) {
            if ($response->headers->has($header)) {
                $response->headers->remove($header);
            }
        }
        
        // Header bag'den direkt olarak da kaldır (case-insensitive kontrol)
        $headers = $response->headers->all();
        foreach ($headers as $key => $value) {
            $normalizedKey = strtolower(str_replace('_', '-', $key));
            if ($normalizedKey === 'x-frame-options') {
                $response->headers->remove($key);
            }
        }
        
        // Symfony HeaderBag'den direkt kaldırma denemesi
        try {
            $reflection = new \ReflectionClass($response->headers);
            $headersProperty = $reflection->getProperty('headers');
            $headersProperty->setAccessible(true);
            $headersArray = $headersProperty->getValue($response->headers);
            
            foreach ($headersArray as $key => $value) {
                if (strtolower($key) === 'x-frame-options') {
                    unset($headersArray[$key]);
                }
            }
            $headersProperty->setValue($response->headers, $headersArray);
        } catch (\Throwable $e) {
            // Reflection hatası - normal yöntemle devam et
        }
    }
    
    /**
     * Modify cookies for iframe embedding (SameSite=None, Secure)
     */
    private function modifyCookiesForIframe(Response $response): void
    {
        try {
            $cookies = $response->headers->getCookies();
            if (empty($cookies)) {
                return;
            }
            
            // Tüm cookie'leri kaldır
            foreach ($cookies as $cookie) {
                $response->headers->removeCookie(
                    $cookie->getName(),
                    $cookie->getPath(),
                    $cookie->getDomain()
                );
            }
            
            // Cookie'leri SameSite=None ve Secure ile tekrar ekle
            foreach ($cookies as $cookie) {
                // Partitioned cookie özelliği - reflection ile kontrol et
                $partitioned = false;
                try {
                    $reflection = new \ReflectionClass($cookie);
                    if ($reflection->hasMethod('getPartitioned')) {
                        $method = $reflection->getMethod('getPartitioned');
                        $partitioned = $method->invoke($cookie);
                    }
                } catch (\Throwable $e) {
                    // Partitioned özelliği yoksa veya hata varsa false kullan
                    $partitioned = false;
                }
                
                $response->headers->setCookie(
                    new Cookie(
                        $cookie->getName(),
                        $cookie->getValue(),
                        $cookie->getExpiresTime(),
                        $cookie->getPath(),
                        $cookie->getDomain(),
                        true, // secure (SameSite=None için zorunlu)
                        $cookie->isHttpOnly(),
                        false, // raw
                        'none', // sameSite
                        $partitioned
                    )
                );
            }
        } catch (\Exception $e) {
            // Cookie modify hatası - log'la ama devam et
            Log::warning('Cookie modification failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}

