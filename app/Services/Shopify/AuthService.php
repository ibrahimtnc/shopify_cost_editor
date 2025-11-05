<?php

namespace App\Services\Shopify;

use App\Models\Shop;
use App\Models\OAuthState;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class AuthService
{
    /**
     * OAuth installation URL oluştur
     */
    public function getInstallationUrl(string $shopDomain): string
    {
        $state = Str::random(40);
        $scopes = config('shopify.scopes');
        $redirectUri = config('shopify.redirect_uri');
        $apiKey = config('shopify.api_key');

        // State'i veritabanına kaydet
        OAuthState::create([
            'state' => $state,
            'shop_domain' => $shopDomain,
            'expires_at' => now()->addMinutes(10),
        ]);

        // Session'a da kaydet (backup için)
        session(['oauth_state' => $state]);
        session(['oauth_shop' => $shopDomain]);

        $params = http_build_query([
            'client_id' => $apiKey,
            'scope' => $scopes,
            'redirect_uri' => $redirectUri,
            'state' => $state,
        ]);

        return "https://{$shopDomain}/admin/oauth/authorize?{$params}";
    }

    /**
     * OAuth callback - access token al
     */
    public function handleCallback(string $shopDomain, string $code, string $state): Shop
    {
        // State verification - veritabanından kontrol et
        $oauthState = OAuthState::where('state', $state)
            ->where('expires_at', '>', now())
            ->first();

        if (!$oauthState || $oauthState->shop_domain !== $shopDomain) {
            throw new \Exception('Invalid or expired OAuth state');
        }

        // Session'dan da kontrol et
        if ($state !== session('oauth_state')) {
            throw new \Exception('Invalid OAuth state');
        }

        // Access token exchange
        $response = Http::post("https://{$shopDomain}/admin/oauth/access_token", [
            'client_id' => config('shopify.api_key'),
            'client_secret' => config('shopify.api_secret'),
            'code' => $code,
        ]);

        if ($response->failed()) {
            throw new \Exception('Failed to get access token: ' . $response->body());
        }

        $data = $response->json();
        $accessToken = $data['access_token'];
        $scope = $data['scope'] ?? config('shopify.scopes');

        // Shop'u kaydet veya güncelle
        $shop = Shop::updateOrCreate(
            ['shop_domain' => $shopDomain],
            [
                'access_token' => $accessToken,
                'scope' => $scope,
                'installed_at' => now(),
                'uninstalled_at' => null,
            ]
        );

        // OAuth state'i sil
        $oauthState->delete();
        session()->forget(['oauth_state', 'oauth_shop']);

        return $shop;
    }

    /**
     * HMAC verification
     */
    public function verifyHmac(array $params, string $hmac): bool
    {
        unset($params['hmac']);
        unset($params['signature']);

        ksort($params);
        $message = http_build_query($params);
        $calculatedHmac = hash_hmac('sha256', $message, config('shopify.api_secret'));

        return hash_equals($calculatedHmac, $hmac);
    }
}

