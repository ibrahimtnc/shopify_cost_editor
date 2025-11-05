@extends('layouts.app')

@section('title', 'Audit Logs - Cost Editor')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="bg-white rounded-lg shadow-md p-6">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold text-gray-800">Audit Logs</h1>
            <div class="text-sm text-gray-600">
                Shop: <strong>{{ $shopDomain ?? 'Not connected' }}</strong>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-gray-50 p-4 rounded-lg mb-6">
            <form method="GET" action="{{ route('logs.index') }}" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label for="field_type" class="block text-sm font-medium text-gray-700 mb-1">Field Type</label>
                    <select name="field_type" id="field_type" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">All</option>
                        @foreach($fieldTypes as $type)
                            <option value="{{ $type }}" {{ $filters['field_type'] === $type ? 'selected' : '' }}>
                                {{ ucfirst($type) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="field_name" class="block text-sm font-medium text-gray-700 mb-1">Field Name</label>
                    <select name="field_name" id="field_name" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">All</option>
                        @foreach($fieldNames as $name)
                            <option value="{{ $name }}" {{ $filters['field_name'] === $name ? 'selected' : '' }}>
                                {{ ucfirst(str_replace('_', ' ', $name)) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="product_id" class="block text-sm font-medium text-gray-700 mb-1">Product ID</label>
                    <input type="text" name="product_id" id="product_id" 
                           value="{{ $filters['product_id'] }}" 
                           placeholder="Filter by product ID"
                           class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>

                <div class="flex items-end">
                    <button type="submit" class="w-full bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700 transition">
                        Filter
                    </button>
                    @if($filters['field_type'] || $filters['field_name'] || $filters['product_id'] || $filters['variant_id'])
                        <a href="{{ route('logs.index') }}" class="ml-2 bg-gray-300 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-400 transition">
                            Clear
                        </a>
                    @endif
                </div>
            </form>
        </div>

        @if($logs->isEmpty())
            <div class="text-center py-12">
                <p class="text-gray-500 text-lg">No audit logs found.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Field</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Variant ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Old Value</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">New Value</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Currency</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($logs as $log)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ $log->created_at->format('Y-m-d H:i:s') }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex flex-col">
                                        <span class="text-sm font-medium text-gray-900">
                                            {{ $log->formatted_field_name }}
                                        </span>
                                        <span class="text-xs text-gray-500">
                                            {{ ucfirst($log->field_type ?? 'N/A') }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    @if($log->product_id)
                                        <div class="flex flex-col">
                                            @if(isset($productNames[$log->product_id]) && $productNames[$log->product_id])
                                                <span class="font-medium text-gray-900" title="{{ $log->product_id }}">
                                                    {{ $productNames[$log->product_id] }}
                                                </span>
                                            @endif
                                            <span class="font-mono text-xs text-gray-400" title="{{ $log->product_id }}">
                                                {{ strlen($log->product_id) > 40 ? substr($log->product_id, 0, 40) . '...' : $log->product_id }}
                                            </span>
                                        </div>
                                    @else
                                        <span class="text-gray-400">N/A</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    @if($log->variant_id)
                                        <div class="flex flex-col">
                                            @if(isset($variantNames[$log->variant_id]) && $variantNames[$log->variant_id])
                                                <span class="font-medium text-gray-900" title="{{ $log->variant_id }}">
                                                    {{ $variantNames[$log->variant_id] }}
                                                </span>
                                            @endif
                                            <span class="font-mono text-xs text-gray-400" title="{{ $log->variant_id }}">
                                                {{ strlen($log->variant_id) > 40 ? substr($log->variant_id, 0, 40) . '...' : $log->variant_id }}
                                            </span>
                                        </div>
                                    @else
                                        <span class="text-gray-400">N/A</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    @if($log->old_value !== null)
                                        <span class="font-semibold text-red-600">
                                            {{ number_format((float)$log->old_value, 2) }}
                                        </span>
                                    @else
                                        <span class="text-gray-400">-</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    @if($log->new_value !== null)
                                        <span class="font-semibold text-green-600">
                                            {{ number_format((float)$log->new_value, 2) }}
                                        </span>
                                    @else
                                        <span class="text-gray-400">-</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ $log->currency_code }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="mt-6">
                {{ $logs->appends(request()->query())->links() }}
            </div>
        @endif
    </div>
</div>
@endsection

