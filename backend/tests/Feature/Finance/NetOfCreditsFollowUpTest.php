<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\InvoiceOverdueNotification;
use App\Notifications\InvoiceReminderNotification;
use App\Services\Admin\ExportService;
use App\Services\MemberService;
use App\Services\Owner\OwnerExportService;
use App\Services\OwnerReportsService;
use App\Support\NotificationPushContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\Concerns\EmulatesMysqlDateFormat;
use Tests\TestCase;

/** Review follow-ups: P&L identity, CSV exports, member payload and reminder amounts. */
class NetOfCreditsFollowUpTest extends TestCase
{
    use EmulatesMysqlDateFormat;
    use RefreshDatabase;

    private Workspace $workspace;

    private User $member;

    private Subscription $subscription;

    protected function setUp(): void
    {
        parent::setUp();

        $this->emulateMysqlDateFormat();
        $this->workspace = Workspace::factory()->create();
        $this->member = User::factory()->freelancer()->create();
        $this->subscription = Subscription::factory()->create([
            'workspace_id' => $this->workspace->id,
            'member_id' => $this->member->id,
        ]);
    }

    /** @param  array<string, mixed>  $attributes */
    private function invoice(array $attributes): Invoice
    {
        return Invoice::factory()->create([...[
            'subscription_id' => $this->subscription->id,
            'workspace_id' => $this->workspace->id,
            'status' => InvoiceStatus::Pending->value,
            'amount' => '100.00',
            'due_date' => Carbon::today()->addDays(3),
        ], ...$attributes]);
    }

    private function csvRows(StreamedResponse $response): array
    {
        ob_start();
        $response->sendContent();
        $csv = preg_replace('/^\xEF\xBB\xBF/', '', (string) ob_get_clean()) ?? '';

        return array_map(
            static fn (string $line): array => str_getcsv($line, ',', '"', ''),
            array_values(array_filter(explode("\n", trim($csv)))),
        );
    }

    public function test_pl_revenue_leaves_out_an_overpayment_without_credits_as_owed_back(): void
    {
        // Paid 130 on a 100 invoice (imported or demo data): the 30 is owed back, as it would be after a credit.
        $this->invoice(['status' => InvoiceStatus::Paid->value, 'paid_at' => now(), 'amount' => '100.00', 'amount_paid' => '130.00']);

        $rows = app(OwnerReportsService::class)->build($this->workspace)['profit_loss'];

        $this->assertEquals(100, end($rows)['revenue']);
    }

    public function test_pl_revenue_drops_only_the_part_owed_back_when_credited(): void
    {
        $this->invoice(['status' => InvoiceStatus::Paid->value, 'paid_at' => now(), 'amount_paid' => '100.00', 'credited_amount' => '30.00']);

        $rows = app(OwnerReportsService::class)->build($this->workspace)['profit_loss'];

        $this->assertEquals(70, end($rows)['revenue']);
    }

    public function test_pl_revenue_keeps_a_partial_payment_on_a_credited_invoice(): void
    {
        // Net 70, paid 50: nothing is owed back, so all 50 is revenue.
        $this->invoice(['status' => InvoiceStatus::PartiallyPaid->value, 'paid_at' => now(), 'amount_paid' => '50.00', 'credited_amount' => '30.00']);

        $rows = app(OwnerReportsService::class)->build($this->workspace)['profit_loss'];

        $this->assertEquals(50, end($rows)['revenue']);
    }

    public function test_admin_invoice_export_appends_credited_and_net_columns(): void
    {
        $this->invoice(['invoice_number' => 'EXP-1', 'amount' => '100.00', 'credited_amount' => '100.00']);

        $rows = $this->csvRows(app(ExportService::class)->stream('invoices', []));

        $this->assertSame(['credited_amount', 'net_amount'], array_slice($rows[0], -2));
        $this->assertSame(['invoice_number', 'member_name', 'workspace_name', 'amount', 'status', 'due_date', 'paid_at'], array_slice($rows[0], 0, 7));
        $this->assertSame(['EXP-1', '100.00', 'pending'], [$rows[1][0], $rows[1][3], $rows[1][4]]);
        $this->assertEquals(100, $rows[1][7]);
        $this->assertEquals(0, $rows[1][8]);
    }

    public function test_owner_invoice_export_appends_credited_and_net_columns(): void
    {
        $this->invoice(['invoice_number' => 'EXP-2', 'amount' => '100.00', 'credited_amount' => '40.00']);

        $rows = $this->csvRows(app(OwnerExportService::class)->stream('invoices', $this->workspace, []));

        $this->assertSame(['credited_amount', 'net_amount'], array_slice($rows[0], -2));
        $this->assertSame(['invoice_number', 'member_name', 'amount', 'status', 'due_date', 'paid_at'], array_slice($rows[0], 0, 6));
        $this->assertSame(['EXP-2', '100.00', 'pending'], [$rows[1][0], $rows[1][2], $rows[1][3]]);
        $this->assertEquals(40, $rows[1][6]);
        $this->assertEquals(60, $rows[1][7]);
    }

    public function test_member_detail_keeps_amount_and_adds_net_amount(): void
    {
        $this->invoice(['amount' => '100.00', 'credited_amount' => '30.00']);

        $invoice = app(MemberService::class)->memberDetail($this->workspace, $this->member)['invoices'][0];

        $this->assertEquals(100, $invoice['amount']);
        $this->assertEquals(70, $invoice['net_amount']);
    }

    public function test_reminder_quotes_what_is_still_owed(): void
    {
        $invoice = $this->invoice(['amount' => '100.00', 'credited_amount' => '30.00', 'amount_paid' => '20.00']);
        $notification = new InvoiceReminderNotification($invoice);

        $lines = implode(' ', $notification->toMail($this->member)->viewData['lines']);

        $this->assertStringContainsString('₪50.00', $lines);
        $this->assertStringNotContainsString('₪100', $lines);

        $payload = $notification->toArray($this->member);
        $this->assertSame('50.00', $payload['amount']);
        // The database payload is also the push text the member reads, in both languages.
        $this->assertPushBody($payload, [
            'en' => "Invoice {$invoice->invoice_number} is due soon (50.00).",
            'ar' => "موعد استحقاق الفاتورة {$invoice->invoice_number} قريب (50.00).",
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $expected  locale => push body
     */
    private function assertPushBody(array $payload, array $expected): void
    {
        foreach ($expected as $locale => $body) {
            app()->setLocale($locale);
            $this->assertSame($body, NotificationPushContent::resolve($payload)[1]);
        }
    }

    public function test_overdue_notification_quotes_what_is_still_owed(): void
    {
        $invoice = $this->invoice([
            'status' => InvoiceStatus::Overdue->value, 'amount' => '100.00', 'credited_amount' => '30.00', 'amount_paid' => '20.00',
        ]);

        foreach (['member', 'owner'] as $audience) {
            $payload = (new InvoiceOverdueNotification($invoice, $audience))->toArray($this->member);

            $this->assertSame('50.00', $payload['amount']);
            $this->assertPushBody($payload, [
                'en' => "Invoice {$invoice->invoice_number} is overdue (50.00).",
                'ar' => "الفاتورة {$invoice->invoice_number} متأخرة (50.00).",
            ]);
        }
    }
}
