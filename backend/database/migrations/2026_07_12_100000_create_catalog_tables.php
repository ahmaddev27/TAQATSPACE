<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Finance catalog: categories (sales or expense) and the items priced inside
 * them. `code` is immutable and unique per workspace, so a code can be reused
 * by another workspace but never twice in the same one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_categories', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20);
            $table->string('code', 50);
            $table->string('name_ar');
            $table->string('name_en');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->unique(['workspace_id', 'code']);
            $table->index(['workspace_id', 'kind', 'sort_order']);
        });

        Schema::create('catalog_items', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('catalog_category_id')->constrained('catalog_categories')->restrictOnDelete();
            $table->string('code', 50);
            $table->string('name_ar');
            $table->string('name_en');
            $table->string('unit', 20);
            $table->decimal('price', 12, 2)->default(0);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->unique(['workspace_id', 'code']);
            $table->index(['workspace_id', 'catalog_category_id']);
            $table->index(['workspace_id', 'archived_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_items');
        Schema::dropIfExists('catalog_categories');
    }
};
