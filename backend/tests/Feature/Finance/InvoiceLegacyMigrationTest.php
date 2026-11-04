<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\Subscription;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Runs the v2 migration over invoices written before it existed (the §6.5
 * status table) by rolling it back, inserting legacy rows and migrating again.
 */
class InvoiceLegacyMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = 'database/migrations/2026_11_05_100000_invoices_v2.php';

    /** @var array<string, array{0: string, 1: string}> */
    private const MAPPING = [
        'pending' => ['issued', 'unpaid'],
        'overdue' => ['issued', 'unpaid'],
        'under_review' => ['issued', 'unpaid'],
        'payment_rejected' => ['issued', 'unpaid'],
        'partially_paid' => ['issued', 'partial'],
        'paid' => ['issued', 'paid'],
        'cancelled' => ['void', 'unpaid'],
    ];

    private function migration(): Migration
    {
        return require base_path(self::MIGRATION);
    }

    public function test_every_legacy_status_maps_to_the_equivalent_pair_with_one_line(): void
    {
        $subscription = Subscription::factory()->create();
        $this->migration()->down();

        $ids = [];
        foreach (array_keys(self::MAPPING) as $index => $status) {
            $ids[$status] = (string) Str::uuid();
            DB::table('invoices')->insert([
                'id' => $ids[$status],
                'subscription_id' => $subscription->id,
                'amount' => 40 + $index,
                'due_date' => '2026-01-15',
                'status' => $status,
                'invoice_number' => "LEGACY-{$index}",
                'created_at' => '2026-01-01 10:00:00',
                'updated_at' => '2026-01-02 10:00:00',
            ]);
        }

        $this->migration()->up();

        foreach (self::MAPPING as $status => [$doc, $payment]) {
            $row = DB::table('invoices')->find($ids[$status]);

            $this->assertSame($doc, $row->doc_status, "doc_status of {$status}");
            $this->assertSame($payment, $row->payment_status, "payment_status of {$status}");
            $this->assertSame($status, $row->status, 'the legacy status is untouched');
            $this->assertSame($subscription->workspace_id, $row->workspace_id);
            $this->assertSame($subscription->member_id, $row->customer_id);
            $this->assertEquals($row->amount, $row->total);
            $this->assertEquals($row->amount, $row->subtotal);
            $this->assertNotNull($row->issued_at);

            $lines = DB::table('invoice_lines')->where('invoice_id', $row->id)->get();
            $this->assertCount(1, $lines, "single line for {$status}");
            $this->assertEquals($row->amount, $lines->first()->amount);
            $this->assertEquals(1, $lines->first()->qty);
        }

        $this->assertNotNull(DB::table('invoices')->find($ids['cancelled'])->voided_at);
        $this->assertNull(DB::table('invoices')->find($ids['paid'])->voided_at);
    }
}
