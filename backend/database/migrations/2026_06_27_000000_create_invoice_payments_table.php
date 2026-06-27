<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-installment payment ledger for an invoice: each recorded payment (amount,
 * its own receipt, and the date it was paid). The invoice's running `amount_paid`
 * is the sum of these; the ledger preserves the full history a single
 * `receipt_path` could not.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_payments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('invoice_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 8, 2);
            $table->string('receipt_path')->nullable();
            $table->timestamp('paid_at');
            $table->timestamps();

            $table->index(['invoice_id', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_payments');
    }
};
