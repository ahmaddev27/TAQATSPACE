<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The café/POS catalogue for a workspace: sellable products (coffee, snacks,
 * printing…) with a price and optional stock tracking. Workspace-scoped, like
 * `workspace_expenses` / `internet_packages`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_products', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->string('name');
            $table->string('category')->nullable();
            $table->string('sku')->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->boolean('track_stock')->default(true);
            $table->integer('stock_qty')->default(0);
            $table->boolean('is_active')->default(true);
            $table->string('image_path')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_products');
    }
};
