@extends('layouts.app')

@section('title', 'Products - Cost Editor')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="bg-white rounded-lg shadow-md p-6">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold text-gray-800">Products</h1>
            <div class="text-sm text-gray-600">
                Shop: <strong>{{ $shopDomain ?? 'Not connected' }}</strong>
            </div>
        </div>

        @if(isset($error))
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <p>{{ $error }}</p>
            </div>
        @endif

        @if(empty($products))
            <div class="text-center py-12">
                <p class="text-gray-500 text-lg">No products found.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Price Range</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cost Range</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Variants</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($products as $edge)
                            @php
                                $product = $edge['node'];
                                $variantCount = $product['totalVariants'] ?? count($product['variants']['edges'] ?? []);
                                $priceRange = $product['priceRangeV2'] ?? null;
                                $minPrice = $priceRange['minVariantPrice']['amount'] ?? null;
                                $maxPrice = $priceRange['maxVariantPrice']['amount'] ?? null;
                                $currency = $priceRange['minVariantPrice']['currencyCode'] ?? 'USD';
                            @endphp
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">{{ $product['title'] }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        {{ $product['status'] === 'ACTIVE' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                        {{ $product['status'] }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    @if($minPrice && $maxPrice)
                                        @if($minPrice === $maxPrice)
                                            <span class="font-semibold">{{ $currency }} {{ number_format((float)$minPrice, 2) }}</span>
                                        @else
                                            <span class="font-semibold">{{ $currency }} {{ number_format((float)$minPrice, 2) }} - {{ number_format((float)$maxPrice, 2) }}</span>
                                        @endif
                                    @else
                                        <span class="text-gray-400">N/A</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    @php
                                        $costRange = $product['costRange'] ?? null;
                                        $minCost = $costRange['minCost'] ?? null;
                                        $maxCost = $costRange['maxCost'] ?? null;
                                        $costCurrency = $costRange['currencyCode'] ?? 'USD';
                                    @endphp
                                    @if($minCost !== null && $maxCost !== null)
                                        @if($minCost === $maxCost)
                                            <span class="font-semibold text-indigo-600">{{ $costCurrency }} {{ number_format((float)$minCost, 2) }}</span>
                                        @else
                                            <span class="font-semibold text-indigo-600">{{ $costCurrency }} {{ number_format((float)$minCost, 2) }} - {{ number_format((float)$maxCost, 2) }}</span>
                                        @endif
                                    @else
                                        <span class="text-gray-400">N/A</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ $variantCount }} variant(s)
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <a href="{{ route('products.edit', ['productId' => urlencode($product['id'])]) }}" 
                                       class="text-indigo-600 hover:text-indigo-900 bg-indigo-50 px-4 py-2 rounded-md hover:bg-indigo-100 transition">
                                        Edit Costs
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($pageInfo['hasNextPage'] ?? false)
                <div class="mt-6 flex justify-center">
                    <a href="{{ route('products.index', ['after' => $pageInfo['endCursor'] ?? null]) }}" 
                       class="bg-indigo-600 text-white px-6 py-2 rounded-md hover:bg-indigo-700 transition">
                        Load More
                    </a>
                </div>
            @endif
        @endif
    </div>
</div>
@endsection

