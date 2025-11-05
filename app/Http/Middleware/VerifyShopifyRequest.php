<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\Shopify\AuthService;
use Symfony\Component\HttpFoundation\Response;

class VerifyShopifyRequest
{
    protected AuthService $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->has('hmac')) {
            $hmac = $request->input('hmac');
            $params = $request->all();
            
            if (!$this->authService->verifyHmac($params, $hmac)) {
                abort(403, 'Invalid HMAC signature');
            }
        }

        return $next($request);
    }
}

