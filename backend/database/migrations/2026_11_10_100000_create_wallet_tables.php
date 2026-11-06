<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The customer wallet: an append-only ledger (the balance is the SUM of a
 * customer's entries) and the numbered vouchers that document money paid back.
 * Money is held in the workspace's currency; no payment gateway is involved.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_entries', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('customer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 20);
            // Signed: positive adds to the balance, negative takes from it.
            $table->decimal('amount', 12, 2);
            // How a deposit was received (cash, transfer, cheque); empty for the other types.
            $table->string('method', 20)->nullable();
            $table->string('reason', 500)->nullable();
            $table->string('related_type', 60)->nullable();
            $table->uuid('related_id')->nullable();
            // Optional client key that makes a repeated request a no-op.
            $table->string('request_key', 64)->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('created_at');

            $table->index(['workspace_id', 'customer_id', 'created_at']);
            $table->index(['related_type', 'related_id']);
            $table->unique(['workspace_id', 'request_key']);
        });

        Schema::create('payment_vouchers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('customer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('number', 60);
            $table->string('method', 20);
            $table->decimal('amount', 12, 2);
            $table->string('reason', 500)->nullable();
            $table->string('reference', 100)->nullable();
            $table->foreignUuid('wallet_entry_id')->nullable()->constrained('wallet_entries')->nullOnDelete();
            $table->dateTime('paid_at');
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('voided_at')->nullable();
            $table->string('void_reason', 500)->nullable();
            $table->timestamps();

            $table->unique(['workspace_id', 'number']);
            $table->index(['workspace_id', 'paid_at']);
            $table->index('customer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_vouchers');
        Schema::dropIfExists('wallet_entries');
    }
};
