<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Running total paid against an invoice, enabling simple partial payments: an
 * invoice with 0 < amount_paid < amount sits in the "partially_paid" state with
 * a remaining balance, until a payment brings it to the full amount (paid).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->decimal('amount_paid', 8, 2)->default(0)->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropColumn('amount_paid');
        });
    }
};
