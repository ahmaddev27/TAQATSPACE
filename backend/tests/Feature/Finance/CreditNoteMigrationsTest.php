<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\CreditNote;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Schema;

/**
 * Runs the two credit-note migrations of this change down and up on a table
 * that holds a note. Schema changes commit implicitly on MariaDB, so the usual
 * wrapping transaction cannot undo these tests: each one forces a fresh
 * database for the next test, otherwise its rows would leak into it.
 */
class CreditNoteMigrationsTest extends ReceiptTestCase
{
    protected function tearDown(): void
    {
        RefreshDatabaseState::$migrated = false;

        parent::tearDown();
    }

    public function test_the_credit_note_migrations_run_down_and_up_and_keep_the_notes(): void
    {
        [$owner, $workspace] = $this->owningWorkspace();
        $invoice = $this->invoiceOf($owner, $workspace, $this->subscriber($workspace), '100.00');
        $note = CreditNote::query()->create([
            'workspace_id' => $workspace->id,
            'invoice_id' => $invoice->id,
            'number' => 'CN-1',
            'amount' => '12.50',
            'reason' => 'Kept across the migrations',
            'request_key' => 'form-1',
            'issued_at' => now(),
        ]);
        $this->assertTrue(Schema::hasColumn('credit_notes', 'request_key'));
        $this->assertFalse(Schema::hasColumn('credit_notes', 'refund_amount'));

        $requestKey = require database_path('migrations/2026_11_10_100001_add_request_key_to_credit_notes.php');
        $dropRefund = require database_path('migrations/2026_11_10_100000_drop_refund_amount_from_credit_notes.php');

        $requestKey->down();
        $dropRefund->down();
        $this->assertFalse(Schema::hasColumn('credit_notes', 'request_key'));
        $this->assertTrue(Schema::hasColumn('credit_notes', 'refund_amount'));

        $dropRefund->up();
        $requestKey->up();
        // Both are guarded, so running them again changes nothing.
        $dropRefund->up();
        $requestKey->up();
        $this->assertTrue(Schema::hasColumn('credit_notes', 'request_key'));
        $this->assertFalse(Schema::hasColumn('credit_notes', 'refund_amount'));

        $kept = CreditNote::query()->findOrFail($note->id);
        $this->assertEquals(12.5, $kept->amount);
        $this->assertSame('Kept across the migrations', $kept->reason);
    }
}
