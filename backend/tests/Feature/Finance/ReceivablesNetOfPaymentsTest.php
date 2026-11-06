<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Enums\DocStatus;
use App\Enums\InvoiceStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Admin\AdminWorkspaceService;
use App\Services\Admin\DashboardStatsService;
use App\Services\Admin\ReportsService;
use App\Services\InvoiceService;
use App\Services\MemberDashboardService;
use App\Services\OwnerReportsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\EmulatesMysqlDateFormat;
use Tests\TestCase;

/**
 * Product-owner decision (2026-11-06): every outstanding, overdue and unpaid
 * figure is the receivable (amount - credited - paid, never below 0) over the
 * owner aging report's statuses, so partial payments are subtracted for every
 * workspace and the dashboards agree with the aging report.
 */
class ReceivablesNetOfPaymentsTest extends TestCase
{
    use RefreshDatabase;
    use EmulatesMysqlDateFormat;

    private Workspace $workspace;

    private User $member;

    private Subscription $subscription;

    protected function setUp(): void
    {
        parent::setUp();

        // Mid-month, so every due date below falls inside the admin report's month window.
        $this->travelTo(Carbon::parse('2026-11-15 10:00:00'));
        $this->emulateMysqlDateFormat();
        $this->workspace = Workspace::factory()->create();
        $this->member = User::factory()->freelancer()->create();
        $this->subscription = Subscription::factory()->create([
            'workspace_id' => $this->workspace->id,
            'member_id' => $this->member->id,
            'status' => SubscriptionStatus::Active,
        ]);
    }

    /** @param  array<string, mixed>  $attributes */
    private function invoice(InvoiceStatus $status, string $amount, int $dueInDays, array $attributes = []): Invoice
    {
        return Invoice::factory()->create([...[
            'subscription_id' => $this->subscription->id,
            'workspace_id' => $this->workspace->id,
            'status' => $status->value,
            'amount' => $amount,
            'paid_at' => $status === InvoiceStatus::Paid ? Carbon::now() : null,
            'due_date' => Carbon::today()->addDays($dueInDays),
        ], ...$attributes]);
    }

    /** @return array<string, float> every receivable figure, keyed by where it is shown */
    private function receivableFigures(): array
    {
        $summary = app(InvoiceService::class)->workspaceSummary($this->workspace);
        $detail = app(AdminWorkspaceService::class)->detailFor($this->workspace)['invoices_summary'];
        $report = app(ReportsService::class)->build();

        return [
            'owner_outstanding' => (float) $summary['total_outstanding'],
            'owner_overdue' => (float) $summary['total_overdue'],
            'admin_dashboard_outstanding' => (float) app(DashboardStatsService::class)->build()['revenue']['outstanding'],
            'admin_report_outstanding' => (float) array_sum(array_column($report['revenue_by_month'], 'outstanding')),
            'admin_workspace_unpaid' => (float) $detail['total_unpaid'],
            'admin_workspace_overdue' => (float) $detail['total_overdue'],
            'member_next_invoice' => (float) (app(MemberDashboardService::class)->summary($this->member)['next_invoice']['amount'] ?? 0),
            'aging_total' => (float) app(OwnerReportsService::class)->build($this->workspace)['aging_total'],
        ];
    }

    public function test_a_pending_invoice_with_a_partial_payment_counts_what_is_left(): void
    {
        $this->invoice(InvoiceStatus::Pending, '100.00', 3, ['amount_paid' => '60.00']);

        $this->assertEquals([
            'owner_outstanding' => 40.0,
            'owner_overdue' => 0.0,
            'admin_dashboard_outstanding' => 40.0,
            'admin_report_outstanding' => 40.0,
            'admin_workspace_unpaid' => 40.0,
            'admin_workspace_overdue' => 0.0,
            'member_next_invoice' => 40.0,
            'aging_total' => 40.0,
        ], $this->receivableFigures());
    }

    public function test_a_partially_paid_overdue_invoice_counts_what_is_left(): void
    {
        // Partially paid and 10 days late: markOverdue never flags it, yet 75 is overdue.
        $this->invoice(InvoiceStatus::PartiallyPaid, '100.00', -10, ['amount_paid' => '25.00']);
        // Flagged overdue with 30 already paid: 50 is overdue.
        $this->invoice(InvoiceStatus::Overdue, '80.00', -5, ['amount_paid' => '30.00']);
        // Partially paid but not due yet: outstanding, not overdue.
        $this->invoice(InvoiceStatus::PartiallyPaid, '50.00', 3, ['amount_paid' => '10.00']);

        $this->assertEquals([
            'owner_outstanding' => 165.0,
            'owner_overdue' => 125.0,
            'admin_dashboard_outstanding' => 165.0,
            'admin_report_outstanding' => 165.0,
            'admin_workspace_unpaid' => 165.0,
            'admin_workspace_overdue' => 125.0,
            'member_next_invoice' => 75.0,
            'aging_total' => 165.0,
        ], $this->receivableFigures());
    }

    public function test_the_dashboard_outstanding_equals_the_aging_total(): void
    {
        $this->invoice(InvoiceStatus::Pending, '100.00', 3, ['amount_paid' => '60.00']);
        $this->invoice(InvoiceStatus::Pending, '50.00', 10);
        $this->invoice(InvoiceStatus::Overdue, '80.00', -40, ['credited_amount' => '20.00']);
        $this->invoice(InvoiceStatus::PartiallyPaid, '90.00', -70, ['amount_paid' => '25.00']);
        // Left out by the aging report, so left out of every outstanding figure too.
        $this->invoice(InvoiceStatus::UnderReview, '70.00', -2);
        $this->invoice(InvoiceStatus::PaymentRejected, '30.00', -2);
        $this->invoice(InvoiceStatus::Paid, '100.00', -20, ['amount_paid' => '100.00']);
        $this->invoice(InvoiceStatus::Cancelled, '40.00', -20);
        $this->invoice(InvoiceStatus::Pending, '200.00', 5, ['doc_status' => DocStatus::Draft->value]);

        $figures = $this->receivableFigures();

        $this->assertEquals(40 + 50 + 60 + 65, $figures['aging_total']);
        $this->assertEquals($figures['aging_total'], $figures['owner_outstanding']);
        $this->assertEquals($figures['aging_total'], $figures['admin_dashboard_outstanding']);
        $this->assertEquals($figures['aging_total'], $figures['admin_report_outstanding']);
    }

    /** Paid, pending and overdue invoices without partial payments or credits keep the old SUM(amount) figures. */
    public function test_invoices_without_partial_payments_or_credits_are_unchanged(): void
    {
        $this->invoice(InvoiceStatus::Paid, '100.00', -20, ['amount_paid' => '100.00']);
        // Legacy paid row: amount_paid was added later and never backfilled.
        $this->invoice(InvoiceStatus::Paid, '40.00', -20, ['amount_paid' => '0.00']);
        $this->invoice(InvoiceStatus::Pending, '80.00', 2);
        $this->invoice(InvoiceStatus::Pending, '20.00', 9);
        $this->invoice(InvoiceStatus::Overdue, '50.00', -3);
        $this->invoice(InvoiceStatus::Overdue, '35.00', -45);

        $old = static fn (array $statuses): float => (float) Invoice::query()
            ->whereIn('status', array_map(static fn (InvoiceStatus $s): string => $s->value, $statuses))
            ->sum('amount');
        $outstanding = $old([InvoiceStatus::Pending, InvoiceStatus::Overdue]);
        $overdue = $old([InvoiceStatus::Overdue]);
        $firstUnpaid = (float) Invoice::query()
            ->whereIn('status', [InvoiceStatus::Pending->value, InvoiceStatus::Overdue->value])
            ->orderBy('due_date')->value('amount');

        $this->assertEquals([
            'owner_outstanding' => $outstanding,
            'owner_overdue' => $overdue,
            'admin_dashboard_outstanding' => $outstanding,
            'admin_report_outstanding' => $outstanding,
            'admin_workspace_unpaid' => $outstanding,
            'admin_workspace_overdue' => $overdue,
            'member_next_invoice' => $firstUnpaid,
            'aging_total' => $outstanding,
        ], $this->receivableFigures());
    }
}
