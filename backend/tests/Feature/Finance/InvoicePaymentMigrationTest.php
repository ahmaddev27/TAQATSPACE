<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Receipt;
use App\Models\Subscription;
use App\Services\Finance\LegacyPaymentImporter;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** The data migration that turns legacy `invoice_payments` rows into receipts. */
class InvoicePaymentMigrationTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: Invoice, 1: Subscription} a legacy invoice with one paid installment */
    private function legacyInvoice(string $amount = '100.00', string $paid = '40.00'): array
    {
        $subscription = Subscription::factory()->create();
        $invoice = Invoice::factory()->create([
            'subscription_id' => $subscription->id,
            'amount' => $amount,
            'amount_paid' => $paid,
        ]);
        InvoicePayment::factory()->create(['invoice_id' => $invoice->id, 'amount' => $paid, 'paid_at' => '2026-02-03 09:30:00']);

        return [$invoice, $subscription];
    }

    public function test_each_payment_becomes_one_receipt_with_one_allocation(): void
    {
        [$invoice, $subscription] = $this->legacyInvoice('100.00', '40.00');
        $payment = InvoicePayment::factory()->withReceipt()->create(['invoice_id' => $invoice->id, 'amount' => '25.50', 'paid_at' => '2026-03-01 08:00:00']);

        $result = app(LegacyPaymentImporter::class)->run();

        $this->assertSame(['migrated' => 2, 'failed' => 0], $result);
        $receipt = Receipt::query()->where('legacy_payment_id', $payment->id)->firstOrFail();
        $this->assertSame($subscription->workspace_id, $receipt->workspace_id);
        $this->assertSame($subscription->member_id, $receipt->customer_id);
        $this->assertEquals('25.50', $receipt->amount);
        $this->assertSame('2026-03-01 08:00:00', $receipt->collected_at->format('Y-m-d H:i:s'));
        $this->assertSame($payment->receipt_path, $receipt->receipt_path);
        $this->assertNotNull($receipt->receipt_disk);
        $this->assertCount(1, $receipt->allocations);
        $this->assertEquals('25.50', $receipt->allocations->first()->amount);
        $this->assertSame($invoice->id, $receipt->allocations->first()->invoice_id);
    }

    public function test_running_it_again_duplicates_nothing_and_the_collected_total_is_unchanged(): void
    {
        $this->legacyInvoice('100.00', '40.00');
        $this->legacyInvoice('80.00', '80.00');
        $importer = app(LegacyPaymentImporter::class);

        $importer->run();
        $before = [Receipt::query()->count(), (float) Receipt::query()->sum('amount'), (float) DB::table('receipt_allocations')->sum('amount')];
        $again = $importer->run();

        $this->assertSame(['migrated' => 0, 'failed' => 0], $again);
        $this->assertSame([2, 120.0, 120.0], $before);
        $this->assertSame($before, [Receipt::query()->count(), (float) Receipt::query()->sum('amount'), (float) DB::table('receipt_allocations')->sum('amount')]);
        $this->assertEquals(120, Invoice::query()->sum('amount_paid'));
        $this->assertSame(2, Receipt::query()->distinct()->count('number'));
    }

    public function test_one_bad_row_is_skipped_and_never_stops_the_rest(): void
    {
        $this->legacyInvoice('100.00', '10.00');
        [$orphan] = $this->legacyInvoice('100.00', '20.00');
        DB::table('invoices')->where('id', $orphan->id)->update(['workspace_id' => null, 'subscription_id' => null]);
        $this->legacyInvoice('100.00', '30.00');

        $result = app(LegacyPaymentImporter::class)->run();

        $this->assertSame(['migrated' => 2, 'failed' => 1], $result);
        $this->assertEquals(40, Receipt::query()->sum('amount'));

        DB::table('invoices')->where('id', $orphan->id)->update(['workspace_id' => Subscription::query()->value('workspace_id')]);
        $this->assertSame(['migrated' => 1, 'failed' => 0], app(LegacyPaymentImporter::class)->run());
    }

    public function test_the_migration_file_copies_existing_rows_and_never_fails_the_deploy(): void
    {
        $this->legacyInvoice('100.00', '40.00');
        $migration = require base_path('database/migrations/2026_11_06_110000_copy_invoice_payments_into_receipts.php');
        $this->assertInstanceOf(Migration::class, $migration);

        $migration->up();
        $migration->up();
        $this->assertSame(1, Receipt::query()->count());

        $this->app->bind(LegacyPaymentImporter::class, static fn () => throw new \RuntimeException('boom'));
        $migration->up();

        $this->assertSame(1, Receipt::query()->count());
    }

    public function test_the_command_reports_what_it_copied(): void
    {
        $this->legacyInvoice();

        $this->artisan('finance:migrate-invoice-payments')->expectsOutputToContain('Copied 1 payment(s); 0 could not')->assertSuccessful();
    }
}
