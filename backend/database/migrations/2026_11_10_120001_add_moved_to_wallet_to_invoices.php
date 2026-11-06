<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How much of what an invoice owes back (InvoiceMoney::owedBack) has already
 * been moved to the customer's wallet. It is the running total of that
 * invoice's wallet entries, written in the same transaction as each entry and
 * under the invoice's row lock, so the wallet sync reads it with the lock it
 * already holds instead of a locking range read over the ledger.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('invoices', 'moved_to_wallet')) {
            return;
        }

        Schema::table('invoices', function (Blueprint $table): void {
            $table->decimal('moved_to_wallet', 12, 2)->default(0)->after('credited_amount');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('invoices', 'moved_to_wallet')) {
            return;
        }

        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropColumn('moved_to_wallet');
        });
    }
};
