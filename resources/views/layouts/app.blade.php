<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Shopify Cost Editor')</title>
    
    <!-- Shopify App Bridge -->
    <script src="https://cdn.shopify.com/shopifycloud/app-bridge@3"></script>
    <script src="https://cdn.shopify.com/shopifycloud/app-bridge-utils@3"></script>
    
    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
        }
        /* Ensure Tailwind classes work properly */
        * {
            box-sizing: border-box;
        }
    </style>
</head>
<body class="bg-gray-50">
    <div id="app">
        @if(session('shop_domain'))
        <!-- Navigation Bar -->
        <nav class="bg-white shadow-sm border-b border-gray-200">
            <div class="container mx-auto px-4">
                <div class="flex justify-between items-center h-16">
                    <div class="flex items-center space-x-8">
                        <a href="{{ route('products.index') }}" class="text-lg font-semibold text-gray-800 hover:text-indigo-600 transition">
                            Cost Editor
                        </a>
                        <div class="flex space-x-4">
                            <a href="{{ route('products.index') }}" 
                               class="px-3 py-2 text-sm font-medium text-gray-700 hover:text-indigo-600 hover:bg-gray-50 rounded-md transition {{ request()->routeIs('products.*') ? 'text-indigo-600 bg-indigo-50' : '' }}">
                                Products
                            </a>
                            <a href="{{ route('logs.index') }}" 
                               class="px-3 py-2 text-sm font-medium text-gray-700 hover:text-indigo-600 hover:bg-gray-50 rounded-md transition {{ request()->routeIs('logs.*') ? 'text-indigo-600 bg-indigo-50' : '' }}">
                                Audit Logs
                            </a>
                        </div>
                    </div>
                    <div class="flex items-center space-x-4">
                        <span class="text-sm text-gray-600">
                            Shop: <strong>{{ session('shop_domain') }}</strong>
                        </span>
                        <form method="POST" action="{{ route('auth.logout') }}" class="inline" id="logout-form">
                            @csrf
                            <button type="submit" 
                                    class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition">
                                Logout
                            </button>
                        </form>
                        <script>
                            // Logout formunu AJAX ile gönder
                            document.getElementById('logout-form')?.addEventListener('submit', async function(e) {
                                e.preventDefault();
                                const form = e.target;
                                const formData = new FormData(form);
                                
                                try {
                                    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
                                    
                                    if (!csrfToken) {
                                        form.submit(); // Fallback to normal form submit
                                        return;
                                    }
                                    
                                    const response = await fetch(form.action, {
                                        method: 'POST',
                                        credentials: 'include', // Cookie'leri gönder (SameSite=None için gerekli)
                                        headers: {
                                            'Accept': 'application/json',
                                            'X-CSRF-TOKEN': csrfToken,
                                            'X-Requested-With': 'XMLHttpRequest'
                                        },
                                        body: formData
                                    });
                                    
                                    if (response.ok) {
                                        const data = await response.json();
                                        // Logout sonrası cache bypass için timestamp ekle
                                        if (data.redirect) {
                                            const separator = data.redirect.includes('?') ? '&' : '?';
                                            window.location.href = data.redirect + separator + 'logout=' + Date.now();
                                        } else {
                                            window.location.href = '/shopify/install?logout=' + Date.now();
                                        }
                                    } else {
                                        // JSON değilse normal form submit yap
                                        form.submit();
                                    }
                                } catch (error) {
                                    // Hata durumunda normal form submit yap
                                    form.submit();
                                }
                            });
                        </script>
                    </div>
                </div>
            </div>
        </nav>
        @endif

        @if(session('success'))
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4 mx-4 mt-4" role="alert">
                <span class="block sm:inline">{{ session('success') }}</span>
            </div>
        @endif

        @if(session('error'))
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4 mx-4 mt-4" role="alert">
                <span class="block sm:inline">{{ session('error') }}</span>
            </div>
        @endif

        @if($errors->any())
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4 mx-4 mt-4" role="alert">
                <ul class="list-disc list-inside">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('content')
    </div>

    <script>
        // Shopify App Bridge initialization
        let isShopifyEmbedded = false;
        let appBridge = null;
        
        // Check if we're in an iframe (Shopify embedded app)
        try {
            isShopifyEmbedded = window.self !== window.top;
        } catch (e) {
            // Cross-origin iframe - definitely embedded
            isShopifyEmbedded = true;
        }
        
        if (isShopifyEmbedded && typeof window !== 'undefined') {
            try {
            const app = window.app = window.app || {};
                if (window['app-bridge']) {
            app.shopify = window['app-bridge'];
                    appBridge = app.shopify;
            
            // Toast notifications için
            if (app.shopify && app.shopify.toast) {
                window.showToast = function(message, isError = false) {
                    app.shopify.toast.show(message, isError ? 'error' : 'success');
                };
                    }
                }
            } catch (err) {
                console.warn('App Bridge initialization failed:', err);
            }
        }
    </script>
</body>
</html>

