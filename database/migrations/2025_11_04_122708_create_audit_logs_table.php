<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Creates audit_logs table for tracking cost, price, and stock changes
     */
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->onDelete('cascade');
            $table->string('product_id')->nullable();
            $table->string('variant_id')->nullable();
            $table->string('inventory_item_id');
            
            // Field type and name for flexible logging
            $table->string('field_type', 20)->nullable(); // cost, price, stock
            $table->string('field_name', 50)->nullable(); // cost, price, stock_on_hand, stock_available
            
            // Generic old/new values
            $table->decimal('old_value', 10, 2)->nullable();
            $table->decimal('new_value', 10, 2)->nullable();
            
            // Specific fields for backward compatibility and clarity
            $table->decimal('old_cost', 10, 2)->nullable();
            $table->decimal('new_cost', 10, 2)->nullable();
            $table->decimal('old_price', 10, 2)->nullable();
            $table->decimal('new_price', 10, 2)->nullable();
            $table->decimal('old_stock', 10, 2)->nullable();
            $table->decimal('new_stock', 10, 2)->nullable();
            
            $table->string('currency_code', 3)->default('USD');
            $table->timestamps();
            
            // Indexes for better query performance
            $table->index('shop_id');
            $table->index('inventory_item_id');
            $table->index('field_type');
            $table->index('field_name');
            $table->index(['shop_id', 'field_type']);
            $table->index('created_at');
            $table->index(['created_at', 'field_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};

