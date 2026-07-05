<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A payment recorded against a POS order (manual tracking — cash or bank
 * transfer, no gateway). Mirrors the `invoice_payments` per-event ledger.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_payments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('pos_order_id')->constrained('pos_orders')->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('method'); // cash | transfer
            $table->foreignUuid('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at');
            $table->timestamps();

            $table->index('pos_order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_payments');
    }
};
