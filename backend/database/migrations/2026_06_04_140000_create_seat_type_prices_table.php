<?php

declare(strict_types=1);

use App\Enums\SeatType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-(workspace, seat type) pricing. Replaces the single
 * `workspaces.price_per_month` as the source of truth for registration,
 * booking and invoicing, while that column is retained as a fallback.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seat_type_prices', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->string('type')->default(SeatType::Flexible->value);
            $table->decimal('price_monthly', 8, 2)->nullable();
            $table->decimal('price_daily', 8, 2)->nullable();
            $table->unsignedSmallInteger('capacity')->default(0);
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->unique(['workspace_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seat_type_prices');
    }
};
