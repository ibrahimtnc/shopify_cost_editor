<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'shopify.verify' => \App\Http\Middleware\VerifyShopifyRequest::class,
            'shopify.auth' => \App\Http\Middleware\ShopifyAuth::class,
            'shopify.embedded' => \App\Http\Middleware\ShopifyEmbeddedHeaders::class,
        ]);
        
        // Apply Shopify embedded headers globally for web routes (en son çalışmalı)
        $middleware->web(append: [
            \App\Http\Middleware\ShopifyEmbeddedHeaders::class,
        ]);
        
        // CSRF token verification için özel middleware
        // Laravel 11 otomatik olarak App\Http\Middleware\VerifyCsrfToken'ı kullanır
        // Eğer varsa, yoksa default middleware'i kullanır
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
