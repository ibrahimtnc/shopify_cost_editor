<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Install Shopify App - Cost Editor</title>
    
    <!-- Shopify App Bridge -->
    <script src="https://cdn.shopify.com/shopifycloud/app-bridge@3"></script>
    <script src="https://cdn.shopify.com/shopifycloud/app-bridge-utils@3"></script>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
        }
    </style>
</head>
<body class="bg-gray-50 flex items-center justify-center min-h-screen">
    <div class="bg-white rounded-lg shadow-lg p-8 max-w-md w-full mx-4">
        <h1 class="text-3xl font-bold text-gray-800 mb-6 text-center">Shopify Cost Editor</h1>
        
        <p class="text-gray-600 mb-6 text-center">
            Connect your Shopify store to manage product costs.
        </p>

        <form method="GET" action="{{ route('shopify.install') }}" class="space-y-4" id="install-form">
            <div>
                <label for="shop" class="block text-sm font-medium text-gray-700 mb-2">
                    Your Shopify Store Domain
                </label>
                <input 
                    type="text" 
                    id="shop" 
                    name="shop" 
                    placeholder="your-store.myshopify.com"
                    class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500"
                    required
                />
                <p class="mt-1 text-sm text-gray-500">
                    Enter your store domain (e.g., your-store.myshopify.com)
                </p>
            </div>

            <button 
                type="submit" 
                class="w-full bg-indigo-600 text-white py-2 px-4 rounded-md hover:bg-indigo-700 transition font-medium">
                Connect Store
            </button>
        </form>

        @if(session('error'))
            <div class="mt-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                <p>{{ session('error') }}</p>
            </div>
        @endif

        @if(session('success'))
            <div class="mt-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
                <p>{{ session('success') }}</p>
            </div>
        @endif
    </div>
</body>
</html>

