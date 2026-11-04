<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Standalone, numbered receipts and their allocation across invoices. A
 * receipt can exist without any allocation (credit on account). The legacy
 * `invoice_payments` rows are copied by a separate, idempotent migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receipts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('customer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('number', 60);
            $table->string('method', 20);
            $table->decimal('amount', 12, 2);
            $table->dateTime('received_at');
            $table->dateTime('collected_at')->nullable();
            $table->string('reference', 100)->nullable();
            $table->string('cheque_status', 20)->nullable();
            $table->string('receipt_path')->nullable();
            $table->string('receipt_disk', 20)->nullable();
            $table->dateTime('voided_at')->nullable();
            $table->string('void_reason', 500)->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('legacy_payment_id')->nullable()->unique();
            $table->timestamps();

            $table->unique(['workspace_id', 'number']);
            $table->index(['workspace_id', 'received_at']);
            $table->index(['workspace_id', 'collected_at']);
            $table->index('customer_id');
        });

        Schema::create('receipt_allocations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('receipt_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('invoice_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->timestamps();

            $table->unique(['receipt_id', 'invoice_id']);
            $table->index('invoice_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipt_allocations');
        Schema::dropIfExists('receipts');
    }
};
