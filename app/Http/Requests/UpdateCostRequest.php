<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCostRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'costs' => 'required|array',
            'costs.*.inventoryItemId' => 'required|string',
            'costs.*.cost' => 'nullable|numeric|min:0',
            'costs.*.currencyCode' => 'nullable|string|size:3',
            'costs.*.variantId' => 'nullable|string',
            'costs.*.price' => 'nullable|numeric|min:0',
            'costs.*.onHand' => 'nullable|integer|min:0',
            'costs.*.oldOnHand' => 'nullable|integer|min:0',
            'costs.*.available' => 'nullable|integer|min:0',
            'costs.*.oldAvailable' => 'nullable|integer|min:0',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'costs.required' => 'Cost data is required',
            'costs.*.inventoryItemId.required' => 'Inventory item ID is required',
                'costs.*.cost.numeric' => 'Cost must be a number',
                'costs.*.cost.min' => 'Cost must be a positive number',
                'costs.*.price.numeric' => 'Price must be a number',
                'costs.*.price.min' => 'Price must be a positive number',
                'costs.*.onHand.integer' => 'Eldeki Miktar must be an integer',
                'costs.*.onHand.min' => 'Eldeki Miktar must be a positive number',
                'costs.*.available.integer' => 'Mevcut must be an integer',
                'costs.*.available.min' => 'Mevcut must be a positive number',
        ];
    }
}
