<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CostAuditLog extends Model
{
    // Use audit_logs table instead of cost_audit_logs
    protected $table = 'audit_logs';

    // Field types for audit log
    const FIELD_TYPE_COST = 'cost';
    const FIELD_TYPE_PRICE = 'price';
    const FIELD_TYPE_STOCK = 'stock';

    protected $fillable = [
        'shop_id',
        'product_id',
        'variant_id',
        'inventory_item_id',
        'field_type', // cost, price, stock
        'field_name', // cost, price, stock_on_hand, stock_available
        'old_value',
        'new_value',
        'currency_code',
        // Specific fields for each type
        'old_cost',
        'new_cost',
        'old_price',
        'new_price',
        'old_stock',
        'new_stock',
    ];

    protected $casts = [
        'old_cost' => 'decimal:2',
        'new_cost' => 'decimal:2',
        'old_price' => 'decimal:2',
        'new_price' => 'decimal:2',
        'old_stock' => 'decimal:2',
        'new_stock' => 'decimal:2',
        'old_value' => 'decimal:2',
        'new_value' => 'decimal:2',
    ];

    /**
     * Shop relationship
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /**
     * Get formatted field name
     */
    public function getFormattedFieldNameAttribute(): string
    {
        return match($this->field_name) {
            'cost' => 'Cost',
            'price' => 'Price',
            'stock_on_hand' => 'Stock (On Hand)',
            'stock_available' => 'Stock (Available)',
            default => ucfirst(str_replace('_', ' ', $this->field_name ?? '')),
        };
    }
}
