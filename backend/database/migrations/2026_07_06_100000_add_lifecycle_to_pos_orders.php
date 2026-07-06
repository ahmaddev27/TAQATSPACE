<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Splits a POS order's lifecycle into two independent axes:
 *  - fulfillment (the `status` column: new -> preparing -> ready -> completed,
 *    plus cancelled/refunded), and
 *  - payment (`paid_at`), which can happen at any point.
 *
 * Adds the refund audit columns and re-maps legacy statuses onto the new set:
 * the old 'pending' becomes 'new', and 'paid' becomes 'completed' (those rows
 * already carry `paid_at`). 'cancelled' is unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_orders', function (Blueprint $table): void {
            $table->timestamp('refunded_at')->nullable()->after('paid_at');
            $table->foreignUuid('refunded_by')->nullable()->after('refunded_at')
                ->constrained('users')->nullOnDelete();
        });

        DB::table('pos_orders')->where('status', 'pending')->update(['status' => 'new']);
        DB::table('pos_orders')->where('status', 'paid')->update(['status' => 'completed']);
    }

    public function down(): void
    {
        DB::table('pos_orders')->where('status', 'completed')->update(['status' => 'paid']);
        DB::table('pos_orders')->where('status', 'new')->update(['status' => 'pending']);

        Schema::table('pos_orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('refunded_by');
            $table->dropColumn('refunded_at');
        });
    }
};
