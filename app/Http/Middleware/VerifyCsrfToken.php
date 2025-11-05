<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;
use Illuminate\Support\Facades\Log;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        // Shopify webhook'ları için
    ];

    /**
     * Determine if the session and input CSRF tokens match.
     */
    protected function tokensMatch($request): bool
    {
        // Shopify embedded app'lerde CSRF token sorunlarını önlemek için
        // Önce _token input'unu kontrol et, sonra X-CSRF-TOKEN header'ını
        $token = $request->input('_token') 
            ?: $request->header('X-CSRF-TOKEN')
            ?: $request->header('X-XSRF-TOKEN');
        
        if (!$token) {
            // Debug için log
            if (config('app.debug')) {
                Log::debug('CSRF token not provided', [
                    'has_session' => $request->hasSession(),
                    'headers' => $request->headers->all(),
                ]);
            }
            return false;
        }

        // Session var mı kontrol et
        if (!$request->hasSession()) {
            if (config('app.debug')) {
                Log::debug('CSRF check failed: No session', [
                    'token_provided' => substr($token, 0, 10) . '...',
                ]);
            }
            return false;
        }

        // Session token'ını al
        try {
            $sessionToken = $request->session()->token();
        } catch (\Exception $e) {
            if (config('app.debug')) {
                Log::debug('CSRF check failed: Session token error', [
                    'error' => $e->getMessage(),
                ]);
            }
            return false;
        }
        
        // Token'ları karşılaştır
        $isMatch = hash_equals($sessionToken, $token);
        
        // Debug için log (production'da kaldırılabilir)
        if (!$isMatch && config('app.debug')) {
            Log::debug('CSRF token mismatch', [
                'session_token' => substr($sessionToken, 0, 10) . '...',
                'provided_token' => substr($token, 0, 10) . '...',
                'has_session' => $request->hasSession(),
                'session_id' => $request->session()->getId(),
                'cookies' => $request->cookies->all(),
            ]);
        }
        
        return $isMatch;
    }
    
    /**
     * Determine if the request should be excluded from CSRF verification.
     */
    protected function inExceptArray($request): bool
    {
        // Shopify embedded app'lerden gelen isteklerde bazı durumlarda exception
        // Ama güvenlik için genelde CSRF kontrolü yapılmalı
        return parent::inExceptArray($request);
    }
}

