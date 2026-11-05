<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Credit notes correct an issued invoice without editing it (BR-FIN-01). The
 * invoice keeps its total; `credited_amount` is the running sum of its credit
 * notes so the receivable is one subtraction away.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_notes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('number', 60);
            $table->decimal('amount', 12, 2);
            // The part of the credit the customer had already paid: owed back to them (wallet credit, task 5.9).
            $table->decimal('refund_amount', 12, 2)->default(0);
            $table->string('reason', 500);
            $table->foreignUuid('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('issued_at');
            $table->dateTime('voided_at')->nullable();
            $table->timestamps();

            $table->unique(['workspace_id', 'number']);
            $table->index(['workspace_id', 'issued_at']);
            $table->index('invoice_id');
        });

        Schema::table('invoices', function (Blueprint $table): void {
            $table->decimal('credited_amount', 12, 2)->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropColumn('credited_amount');
        });
        Schema::dropIfExists('credit_notes');
    }
};
