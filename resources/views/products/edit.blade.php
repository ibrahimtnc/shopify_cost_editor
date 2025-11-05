@extends('layouts.app')

@section('title', 'Edit Product - Cost Editor')

@section('content')
<div class="container mx-auto px-4 py-8" 
     x-data="productEditor()"
     x-init="selectedLocationId = '{{ $selectedLocationId ?? '' }}'">
    <!-- Success/Error Toast Notification -->
    <div x-show="showToast" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 transform translate-y-2"
         x-transition:enter-end="opacity-100 transform translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed top-4 right-4 z-50 max-w-sm w-full"
         x-cloak>
        <div :class="toastType === 'success' ? 'bg-green-50 border-green-200 text-green-800' : 'bg-red-50 border-red-200 text-red-800'"
             class="border rounded-lg shadow-lg p-4 flex items-center justify-between">
            <div class="flex items-center">
                <svg :class="toastType === 'success' ? 'text-green-400' : 'text-red-400'" class="w-5 h-5 mr-3" fill="currentColor" viewBox="0 0 20 20">
                    <path v-if="toastType === 'success'" fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    <path v-else fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                </svg>
                <p x-text="toastMessage" class="text-sm font-medium"></p>
            </div>
            <button @click="showToast = false" class="ml-4 text-gray-400 hover:text-gray-600">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                </svg>
            </button>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-md p-6">
        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-3xl font-bold text-gray-800">Edit Product</h1>
                <p class="text-gray-600 mt-1">{{ $product['title'] ?? 'Product' }}</p>
                @if(isset($product['priceRangeV2']))
                    @php
                        $priceRange = $product['priceRangeV2'];
                        $minPrice = $priceRange['minVariantPrice']['amount'] ?? null;
                        $maxPrice = $priceRange['maxVariantPrice']['amount'] ?? null;
                        $currency = $priceRange['minVariantPrice']['currencyCode'] ?? 'USD';
                    @endphp
                    @if($minPrice && $maxPrice)
                        <p class="text-sm text-gray-500 mt-1">
                            Price Range: 
                            @if($minPrice === $maxPrice)
                                <span class="font-semibold">{{ $currency }} {{ number_format((float)$minPrice, 2) }}</span>
                            @else
                                <span class="font-semibold">{{ $currency }} {{ number_format((float)$minPrice, 2) }} - {{ number_format((float)$maxPrice, 2) }}</span>
                            @endif
                        </p>
                    @endif
                @endif
            </div>
            <a href="{{ route('products.index') }}" class="text-gray-600 hover:text-gray-800 flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Back to Products
            </a>
        </div>
        
        <!-- Location Selector -->
        @if(isset($locations) && count($locations) > 0)
        <div class="mb-6 bg-gray-50 rounded-lg p-4 border border-gray-200">
            <label class="block text-sm font-medium text-gray-700 mb-2">
                <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                Inventory Location
            </label>
            <select 
                x-model="selectedLocationId"
                @change="loadProductForLocation()"
                class="block w-full max-w-md rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                @foreach($locations as $location)
                    <option value="{{ $location['id'] }}" {{ $location['id'] === $selectedLocationId ? 'selected' : '' }}>
                        {{ $location['name'] }}
                    </option>
                @endforeach
            </select>
            <p class="text-xs text-gray-500 mt-2">
                Select the location to view and update inventory quantities for this product.
            </p>
        </div>
        @endif

        <form @submit.prevent="saveAll" class="mt-6">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">SKU</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Variant</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Selling Price</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stock<br/>at Selected Location</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Stock<br/><span class="text-xs text-gray-400 font-normal">(All Locations)</span></th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Current Cost</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">New Cost</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($variants as $edge)
                            @php
                                $variant = $edge['node'];
                                $sku = $variant['sku'] ?? 'N/A';
                                $price = $variant['price'] ?? '0.00';
                                $compareAtPrice = $variant['compareAtPrice'] ?? null;
                                
                                // Inventory bilgilerini güvenli şekilde al
                                $inventoryItem = $variant['inventoryItem'] ?? null;
                                $unitCost = $inventoryItem['unitCost'] ?? null;
                                $currentCost = $unitCost['amount'] ?? '0.00';
                                $currencyCode = $unitCost['currencyCode'] ?? 'USD';
                                $inventoryItemId = $inventoryItem['id'] ?? null;
                                $variantId = $variant['id'] ?? null;
                                
                                // Get on_hand and available quantities at selected location
                                // Backend'de set edilen değerleri kullan (inventoryOnHand, inventoryAvailable)
                                // Eğer yoksa fallback olarak inventoryQuantity kullanma - direkt 0 göster
                                $onHand = isset($variant['inventoryOnHand']) ? (int)$variant['inventoryOnHand'] : 0;
                                $available = isset($variant['inventoryAvailable']) ? (int)$variant['inventoryAvailable'] : 0;
                                
                                // Total across all locations
                                $totalOnHand = isset($variant['totalInventoryOnHand']) ? (int)$variant['totalInventoryOnHand'] : 0;
                                $totalAvailable = isset($variant['totalInventoryAvailable']) ? (int)$variant['totalInventoryAvailable'] : 0;
                            @endphp
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $sku }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-medium">{{ $variant['title'] }}</td>
                                
                                <!-- Selling Price -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($variantId)
                                        <div class="flex items-center space-x-2">
                                            <span class="text-sm text-gray-500">{{ $currencyCode }}</span>
                                            <input 
                                                type="number" 
                                                step="0.01"
                                                min="0"
                                                x-model="variants['{{ $inventoryItemId }}'].price"
                                                value="{{ $price }}"
                                                class="form-input rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 w-24 text-sm"
                                                placeholder="0.00"
                                            />
                                        </div>
                                    @else
                                        <span class="text-gray-400 text-sm">N/A</span>
                                    @endif
                                </td>
                                
                                <!-- Stock at Selected Location (editable) -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($inventoryItemId && isset($selectedLocationId))
                                        <input 
                                            type="number" 
                                            min="0"
                                            step="1"
                                            x-model="variants['{{ $inventoryItemId }}'].onHand"
                                            value="{{ $onHand }}"
                                            class="form-input rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 w-20 text-sm"
                                            placeholder="0"
                                        />
                                    @else
                                        <span class="text-gray-400 text-sm">{{ $onHand }}</span>
                                    @endif
                                </td>
                                
                                <!-- Total Stock (All Locations) - Read-only -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="text-sm font-semibold text-gray-700 bg-gray-100 px-3 py-1 rounded">
                                        {{ $totalOnHand }}
                                    </span>
                                </td>
                                
                                <!-- Current Cost -->
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    @if($inventoryItemId)
                                        {{ $currencyCode }} {{ number_format((float)$currentCost, 2) }}
                                    @else
                                        <span class="text-red-500">N/A</span>
                                    @endif
                                </td>
                                
                                <!-- New Cost -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($inventoryItemId)
                                        <div class="flex items-center space-x-2">
                                            <span class="text-sm text-gray-500">{{ $currencyCode }}</span>
                                            <input 
                                                type="number" 
                                                step="0.01"
                                                min="0"
                                                x-model="variants['{{ $inventoryItemId }}'].cost"
                                                value="{{ $currentCost }}"
                                                class="form-input rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 w-24 text-sm"
                                                required
                                                placeholder="0.00"
                                            />
                                        </div>
                                    @else
                                        <span class="text-red-500 text-sm">N/A</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-6 flex justify-end space-x-4">
                <a href="{{ route('products.index') }}" 
                   class="bg-gray-200 text-gray-800 px-6 py-2 rounded-md hover:bg-gray-300 transition">
                    Cancel
                </a>
                <button 
                    type="submit" 
                    :disabled="loading"
                    class="bg-indigo-600 text-white px-6 py-2 rounded-md hover:bg-indigo-700 transition disabled:opacity-50 disabled:cursor-not-allowed flex items-center">
                    <svg x-show="loading" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span x-show="!loading">Save All Changes</span>
                    <span x-show="loading">Saving...</span>
                </button>
            </div>
        </form>
    </div>
</div>

@php
    $selectedLocationId = $selectedLocationId ?? ($locations[0]['id'] ?? null);
    $variantsData = collect($variants)->mapWithKeys(function($edge) use ($selectedLocationId) {
        $variant = $edge['node'];
        $inventoryItem = $variant['inventoryItem'] ?? null;
        
        // Sadece inventory item'ı olan variant'ları ekle
        if (!$inventoryItem || !isset($inventoryItem['id'])) {
            return [];
        }
        
        $inventoryItemId = $inventoryItem['id'];
        $unitCost = $inventoryItem['unitCost'] ?? null;
        $currentCost = $unitCost['amount'] ?? '0.00';
        $currencyCode = $unitCost['currencyCode'] ?? 'USD';
        $price = $variant['price'] ?? '0.00';
        
        // Get on_hand and available quantities at selected location
        // Backend'de set edilen değerleri kullan (inventoryOnHand, inventoryAvailable)
        // Eğer yoksa fallback olarak inventoryQuantity kullanma - direkt 0 göster
        $onHand = isset($variant['inventoryOnHand']) ? (int)$variant['inventoryOnHand'] : 0;
        $available = isset($variant['inventoryAvailable']) ? (int)$variant['inventoryAvailable'] : 0;
        
        // Get total quantities across all locations
        $totalOnHand = isset($variant['totalInventoryOnHand']) ? (int)$variant['totalInventoryOnHand'] : 0;
        $totalAvailable = isset($variant['totalInventoryAvailable']) ? (int)$variant['totalInventoryAvailable'] : 0;
        
        $variantId = $variant['id'] ?? null;
        
        return [
            $inventoryItemId => [
                'inventoryItemId' => $inventoryItemId,
                'variantId' => $variantId,
                'cost' => (float)$currentCost,
                'oldCost' => (float)$currentCost,
                'price' => (float)$price,
                'oldPrice' => (float)$price,
                'onHand' => (int)$onHand,
                'oldOnHand' => (int)$onHand,
                // available artık güncellenmiyor - sadece gösterge olarak tutuluyor
                'totalOnHand' => (int)$totalOnHand,
                'totalAvailable' => (int)$totalAvailable,
                'currencyCode' => $currencyCode,
                'locationId' => $selectedLocationId,
            ]
        ];
    })->filter()->toArray();
    $encodedProductId = urlencode($product['id'] ?? '');
    $productIdForUpdate = $product['id'] ?? '';
@endphp

<div data-variants='{!! json_encode($variantsData) !!}' 
     data-product-id='{!! json_encode($encodedProductId) !!}'
     data-product-id-update='{!! json_encode($productIdForUpdate) !!}'
     data-selected-location-id='{!! json_encode($selectedLocationId ?? '') !!}'
     style="display: none;"></div>

<script>
function productEditor() {
    const dataDiv = document.querySelector('[data-variants]');
    const variantsData = JSON.parse(dataDiv.getAttribute('data-variants'));
    const encodedProductId = JSON.parse(dataDiv.getAttribute('data-product-id'));
    const productIdForUpdate = JSON.parse(dataDiv.getAttribute('data-product-id-update'));
    const selectedLocationId = JSON.parse(dataDiv.getAttribute('data-selected-location-id'));
    
    return {
        variants: variantsData,
        selectedLocationId: selectedLocationId,
        loading: false,
        showToast: false,
        toastMessage: '',
        toastType: 'success',
        
        loadProductForLocation() {
            // Location değiştiğinde sayfayı yeniden yükle
            const url = new URL(window.location.href);
            url.searchParams.set('location_id', this.selectedLocationId);
            window.location.href = url.toString();
        },
        
        showNotification(message, isError = false) {
            this.toastMessage = message;
            this.toastType = isError ? 'error' : 'success';
            this.showToast = true;
            setTimeout(() => {
                this.showToast = false;
            }, 5000);
        },
        
        async saveAll() {
                // Validation
                const errors = [];
                Object.values(this.variants).forEach(v => {
                    if (v.cost !== null && v.cost !== '' && (isNaN(v.cost) || v.cost < 0)) {
                        errors.push('Cost must be a positive number');
                    }
                    if (v.price !== null && v.price !== '' && (isNaN(v.price) || v.price < 0)) {
                        errors.push('Price must be a positive number');
                    }
                    if (v.onHand !== null && v.onHand !== '' && (isNaN(v.onHand) || v.onHand < 0)) {
                        errors.push('Stock must be a positive integer');
                    }
                });
            
            if (errors.length > 0) {
                this.showNotification('Validation errors: ' + errors[0], true);
                return;
            }
            
            this.loading = true;
            
            try {
                // Sadece değişen değerleri gönder
                const costs = Object.values(this.variants)
                    .map(v => {
                        const hasChanges = {
                            cost: v.cost !== null && v.cost !== '' && parseFloat(v.cost) !== parseFloat(v.oldCost || 0),
                            price: v.price !== null && v.price !== '' && parseFloat(v.price) !== parseFloat(v.oldPrice || 0),
                            onHand: v.onHand !== null && v.onHand !== '' && parseInt(v.onHand) !== parseInt(v.oldOnHand || 0),
                        };
                        
                        // Eğer hiçbir değişiklik yoksa null döndür
                        if (!hasChanges.cost && !hasChanges.price && !hasChanges.onHand) {
                            return null;
                        }
                        
                        return {
                    inventoryItemId: v.inventoryItemId,
                    variantId: v.variantId,
                            // Sadece değişen değerleri gönder
                            cost: hasChanges.cost ? parseFloat(v.cost) : null,
                            oldCost: hasChanges.cost ? parseFloat(v.oldCost || 0) : null,
                            price: hasChanges.price ? parseFloat(v.price) : null,
                            oldPrice: hasChanges.price ? parseFloat(v.oldPrice || 0) : null,
                            onHand: hasChanges.onHand ? parseInt(v.onHand) : null,
                            oldOnHand: hasChanges.onHand ? parseInt(v.oldOnHand || 0) : null,
                    currencyCode: v.currencyCode,
                            locationId: this.selectedLocationId || v.locationId,
                            productId: productIdForUpdate,
                        };
                    })
                    .filter(v => v !== null); // Null olanları filtrele

                // Eğer hiç değişiklik yoksa uyar
                if (costs.length === 0) {
                    this.showNotification('No changes detected. Please modify at least one value before saving.', true);
                    this.loading = false;
                    return;
                }

                const productId = encodedProductId;
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
                
                if (!csrfToken) {
                    throw new Error('CSRF token not found. Please refresh the page.');
                }
                
                const response = await fetch(`/products/${productId}/cost`, {
                    method: 'POST',
                    credentials: 'include', // Cookie'leri gönder (SameSite=None için gerekli)
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ costs })
                });
                
                // Response'u kontrol et
                if (!response.ok) {
                    const contentType = response.headers.get('content-type');
                    if (contentType && contentType.includes('application/json')) {
                        const errorData = await response.json();
                        throw new Error(errorData.message || `HTTP error! status: ${response.status}`);
                    } else {
                        // HTML response ise (redirect olmuş)
                        throw new Error(`Authentication required. Please refresh the page.`);
                    }
                }
                
                const data = await response.json();
                
                if (data.success) {
                    const updated = data.updated || {};
                    const messages = [];
                    if (updated.costs > 0) messages.push(`${updated.costs} cost(s)`);
                    if (updated.prices > 0) messages.push(`${updated.prices} price(s)`);
                    if (updated.stocks > 0) messages.push(`${updated.stocks} stock level(s)`);
                    
                    this.showNotification(
                        'Successfully updated: ' + messages.join(', '),
                        false
                    );
                    
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    this.showNotification(
                        data.message || 'Error updating product information',
                        true
                    );
                }
            } catch (error) {
                this.showNotification(
                    'Failed to update: ' + error.message,
                    true
                );
            } finally {
                this.loading = false;
            }
        }
    }
}
</script>
@endsection
