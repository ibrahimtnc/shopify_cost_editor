<?php

namespace App\Services\Shopify;

use App\Models\Shop;
use App\Exceptions\ShopifyApiException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ShopifyService
{
    protected string $apiVersion;

    public function __construct()
    {
        $this->apiVersion = config('shopify.api_version', '2024-10');
    }

    /**
     * GraphQL query/mutation çalıştır
     * Profesyonel error handling ve validation ile
     */
    public function executeGraphQL(string $shopDomain, string $query, ?array $variables = null): array
    {
        $accessToken = $this->getAccessToken($shopDomain);
        
        // Variables null veya boş array ise hiç gönderme
        $payload = [
            'query' => $query,
        ];
        
        // Variables varsa ve boş değilse ekle
        if ($variables !== null && is_array($variables) && count($variables) > 0) {
            $payload['variables'] = $variables;
        }
        
        // Request timeout ve retry ayarları
        $response = Http::timeout(30)
            ->retry(2, 1000)
            ->withHeaders([
                'X-Shopify-Access-Token' => $accessToken,
                'Content-Type' => 'application/json',
            ])->post("https://{$shopDomain}/admin/api/{$this->apiVersion}/graphql.json", $payload);

        // HTTP hata kontrolü
        if ($response->failed()) {
            $statusCode = $response->status();
            $errorBody = $response->body();
            
            Log::error('Shopify API HTTP Error', [
                'status' => $statusCode,
                'body' => $errorBody,
                'shop' => $shopDomain,
                'query' => substr($query, 0, 200) . '...',
            ]);
            
            // Özel hata mesajları
            if ($statusCode === 401) {
                throw new ShopifyApiException('Unauthorized: Invalid or expired access token');
            } elseif ($statusCode === 403) {
                throw new ShopifyApiException('Forbidden: Insufficient permissions');
            } elseif ($statusCode === 429) {
                throw new ShopifyApiException('Rate limit exceeded. Please try again later.');
            } else {
                throw new ShopifyApiException("Shopify API request failed (HTTP {$statusCode}): " . substr($errorBody, 0, 200));
            }
        }

        $data = $response->json();
        
        // GraphQL hata kontrolü
        if (isset($data['errors']) && !empty($data['errors'])) {
            $errorMessages = array_map(function($error) {
                $message = $error['message'] ?? json_encode($error);
                if (isset($error['extensions']['code'])) {
                    $message .= ' [Code: ' . $error['extensions']['code'] . ']';
                }
                return $message;
            }, $data['errors']);
            
            Log::error('Shopify GraphQL Errors', [
                'errors' => $data['errors'],
                'shop' => $shopDomain,
                'query' => $query,
                'variables' => $variables,
            ]);
            
            throw new ShopifyApiException('GraphQL errors: ' . implode(', ', $errorMessages));
        }

        // Rate limit kontrolü ve uyarı
        $this->checkRateLimit($response);

        // Data validation
        if (!isset($data['data'])) {
            Log::warning('Shopify API response missing data', [
                'response' => $data,
                'shop' => $shopDomain,
            ]);
            return [];
        }

        return $data['data'];
    }

    /**
     * Shop'dan access token al
     */
    protected function getAccessToken(string $shopDomain): string
    {
        $shop = Shop::where('shop_domain', $shopDomain)->first();
        
        if (!$shop || !$shop->access_token) {
            throw new ShopifyApiException('Shop not found or not authenticated');
        }

        return $shop->access_token;
    }

    /**
     * Rate limit kontrolü
     */
    protected function checkRateLimit($response): void
    {
        $callLimit = $response->header('X-Shopify-Shop-Api-Call-Limit');
        
        if ($callLimit) {
            [$used, $total] = explode('/', $callLimit);
            $usage = ($used / $total) * 100;
            
            if ($usage > 80) {
                Log::warning('Shopify rate limit approaching', [
                    'used' => $used,
                    'total' => $total,
                    'usage' => $usage . '%',
                ]);
                
                // Rate limit yaklaşıyorsa bekle
                sleep(2);
            }
        }
    }
}

