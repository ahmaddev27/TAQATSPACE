<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Immutable audit ledger of every change to a product's stock: manual
 * adjustments/restocks by staff and automatic decrements when an order is sold.
 * Mirrors the `invoice_payments` per-event ledger pattern.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_stock_movements', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('pos_product_id')->constrained('pos_products')->cascadeOnDelete();
            $table->string('type'); // restock | adjustment | sale
            $table->integer('qty_change'); // signed: +restock, -sale
            $table->integer('stock_after');
            $table->string('note')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('pos_product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_stock_movements');
    }
};
