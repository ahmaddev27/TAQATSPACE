<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A line on a POS order. Name + unit price are SNAPSHOTTED at sale time so
 * historical orders stay correct even if the product is later renamed, repriced
 * or deleted (the product FK is nullable + nullOnDelete for that reason).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_order_items', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('pos_order_id')->constrained('pos_orders')->cascadeOnDelete();
            $table->foreignUuid('pos_product_id')->nullable()->constrained('pos_products')->nullOnDelete();
            $table->string('name');
            $table->decimal('unit_price', 10, 2);
            $table->integer('qty');
            $table->decimal('line_total', 10, 2);
            $table->timestamps();

            $table->index('pos_order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_order_items');
    }
};
