<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\CostController;
use App\Http\Middleware\VerifyShopifyRequest;
use App\Http\Middleware\ShopifyAuth;

// Shopify OAuth Routes
Route::get('/shopify/install', [AuthController::class, 'install'])->name('shopify.install');
Route::get('/shopify/callback', [AuthController::class, 'callback'])
    ->middleware(VerifyShopifyRequest::class)
    ->name('shopify.callback');

// Product Routes (Shopify Auth required)
Route::middleware([ShopifyAuth::class])->group(function () {
    Route::get('/', [ProductController::class, 'index'])->name('products.index');
    Route::get('/products', [ProductController::class, 'index'])->name('products.index');
    Route::get('/products/{productId}/edit', [ProductController::class, 'edit'])
        ->where('productId', '.*')
        ->name('products.edit');
    
    // Cost Update Route
    Route::post('/products/{productId}/cost', [CostController::class, 'update'])
        ->where('productId', '.*')
        ->name('cost.update');
    
    // Audit Log Routes
    Route::get('/logs', [\App\Http\Controllers\LogController::class, 'index'])->name('logs.index');
    
    // Logout Route (POST ve GET destekli)
    Route::match(['get', 'post'], '/logout', [AuthController::class, 'logout'])->name('auth.logout');
});
