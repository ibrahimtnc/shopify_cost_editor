<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Shopify embedded app için session cookie ayarları
        // Request'ten gelen Shopify kontrolü middleware'de yapılacak
        // Burada sadece genel ayarları yapıyoruz
        
        // Session middleware'in cookie ayarlarını Shopify için optimize et
        // Bu, session middleware'in cookie'leri oluştururken kullanılacak
    }
}
