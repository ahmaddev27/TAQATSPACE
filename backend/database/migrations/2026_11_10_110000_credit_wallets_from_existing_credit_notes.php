<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Credit notes issued before the wallet existed stored the part the customer had
 * already paid only as `refund_amount`. Turn each into the wallet credit new notes
 * now create, once: a note that already has its entry is skipped.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('credit_notes')
            ->join('invoices', 'invoices.id', '=', 'credit_notes.invoice_id')
            ->where('credit_notes.refund_amount', '>', 0)
            ->whereNull('credit_notes.voided_at')
            ->whereNotNull('invoices.customer_id')
            ->whereNotExists(fn ($query) => $query->select(DB::raw(1))
                ->from('wallet_entries')
                ->whereColumn('wallet_entries.related_id', 'credit_notes.id')
                ->where('wallet_entries.related_type', 'credit_note'))
            ->orderBy('credit_notes.id')
            ->select('credit_notes.id', 'credit_notes.workspace_id', 'credit_notes.refund_amount', 'credit_notes.number', 'credit_notes.issued_at', 'invoices.customer_id')
            ->chunkById(200, function ($notes): void {
                DB::table('wallet_entries')->insert($notes->map(static fn (object $note): array => [
                    'id' => (string) Str::uuid(),
                    'workspace_id' => $note->workspace_id,
                    'customer_id' => $note->customer_id,
                    'type' => 'credit',
                    'amount' => $note->refund_amount,
                    'reason' => 'Credit note '.$note->number,
                    'related_type' => 'credit_note',
                    'related_id' => $note->id,
                    'created_at' => $note->issued_at,
                ])->all());
            }, 'credit_notes.id', 'id');
    }

    public function down(): void
    {
        DB::table('wallet_entries')->where('related_type', 'credit_note')->where('type', 'credit')->delete();
    }
};
