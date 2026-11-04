<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Workspace financial plans. `code` is immutable and unique per workspace.
 * Money columns are decimal(12,2); `included_quantity` is hours or GB
 * depending on the pricing method.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('code', 50);
            $table->string('name_ar');
            $table->string('name_en');
            $table->string('pricing_method', 20);
            $table->decimal('price', 12, 2)->default(0);
            $table->decimal('overage_price', 12, 2)->nullable();
            $table->decimal('included_quantity', 12, 2)->nullable();
            $table->unsignedInteger('validity_days')->nullable();
            $table->boolean('is_dedicated_seat')->default(false);
            $table->decimal('promo_price', 12, 2)->nullable();
            $table->dateTime('promo_until')->nullable();
            $table->dateTime('archived_at')->nullable();
            $table->timestamps();

            $table->unique(['workspace_id', 'code']);
            $table->index(['workspace_id', 'archived_at']);
            $table->index(['workspace_id', 'pricing_method']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
