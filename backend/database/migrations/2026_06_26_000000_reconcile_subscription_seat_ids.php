<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * One-time reconciliation: earlier, freeing a seat cleared the seat row but not
 * the holding subscription's `seat_id`, leaving members shown as still seated
 * (and hidden from the seat-assignment picker). Null out any `seat_id` that no
 * longer points at a seat actually assigned to that member.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('subscriptions')
            ->whereNotNull('seat_id')
            ->whereNotExists(function ($query): void {
                $query->select(DB::raw(1))
                    ->from('seats')
                    ->whereColumn('seats.id', 'subscriptions.seat_id')
                    ->whereColumn('seats.assigned_member_id', 'subscriptions.member_id');
            })
            ->update(['seat_id' => null]);
    }

    public function down(): void
    {
        // Data reconciliation — nothing to reverse.
    }
};
