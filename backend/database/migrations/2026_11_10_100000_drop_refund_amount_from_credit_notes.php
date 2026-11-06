<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The amount owed back to a customer is derived from the invoice on every read
 * (InvoiceBalanceService::refundDue). A value frozen on the credit note when it
 * was issued went stale as soon as a cheque bounced or a receipt was voided.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('credit_notes', 'refund_amount')) {
            Schema::table('credit_notes', function (Blueprint $table): void {
                $table->dropColumn('refund_amount');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('credit_notes', 'refund_amount')) {
            Schema::table('credit_notes', function (Blueprint $table): void {
                $table->decimal('refund_amount', 12, 2)->default(0)->after('amount');
            });
        }
    }
};
