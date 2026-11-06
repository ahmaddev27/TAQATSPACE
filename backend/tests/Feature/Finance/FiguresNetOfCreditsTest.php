<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

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
use App\Services\OwnerDashboardService;
use App\Services\OwnerReportsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\EmulatesMysqlDateFormat;
use Tests\TestCase;

/**
 * Every money figure must be net of credit notes (invoices.credited_amount),
 * and stay exactly as before for invoices without credits.
 */
class FiguresNetOfCreditsTest extends TestCase
{
    use RefreshDatabase;
    use EmulatesMysqlDateFormat;

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
            'status' => SubscriptionStatus::Active,
        ]);
    }

    /** @param  array<string, mixed>  $attributes */
    private function invoice(InvoiceStatus $status, string $amount, array $attributes = []): Invoice
    {
        return Invoice::factory()->create([...[
            'subscription_id' => $this->subscription->id,
            'workspace_id' => $this->workspace->id,
            'status' => $status->value,
            'amount' => $amount,
            'paid_at' => $status === InvoiceStatus::Paid ? Carbon::now() : null,
            'due_date' => Carbon::today()->addDays(5),
        ], ...$attributes]);
    }

    /**
     * Collected: 100 + 70 + 0 = 170. Outstanding: 70 + 30 = 100. Overdue: 30 (also the soonest-due unpaid invoice).
     */
    private function seedCredited(): void
    {
        $this->invoice(InvoiceStatus::Paid, '100.00', ['amount_paid' => '100.00']);
        // Paid in full, then credited 30: 30 is owed back, not revenue.
        $this->invoice(InvoiceStatus::Paid, '100.00', ['amount_paid' => '100.00', 'credited_amount' => '30.00']);
        // Fully credited and never paid (or cheque bounced): settles to paid, collects nothing.
        $this->invoice(InvoiceStatus::Paid, '100.00', ['amount_paid' => '0.00', 'credited_amount' => '100.00']);
        $this->invoice(InvoiceStatus::Pending, '100.00', ['credited_amount' => '30.00', 'due_date' => Carbon::today()->addDay()]);
        $this->invoice(InvoiceStatus::Overdue, '50.00', ['credited_amount' => '20.00', 'due_date' => Carbon::today()->subDays(3)]);
    }

    /** Paid, legacy paid (amount_paid never backfilled), partly paid, overdue and pending - no credits. */
    private function seedPlain(): void
    {
        $this->invoice(InvoiceStatus::Paid, '100.00', ['amount_paid' => '100.00']);
        $this->invoice(InvoiceStatus::Paid, '40.00', ['amount_paid' => '0.00']);
        $this->invoice(InvoiceStatus::Pending, '80.00', ['amount_paid' => '30.00', 'due_date' => Carbon::today()->addDay()]);
        $this->invoice(InvoiceStatus::Overdue, '50.00', ['due_date' => Carbon::today()->subDays(3)]);
        $this->invoice(InvoiceStatus::Pending, '20.00', ['due_date' => Carbon::today()->addDays(9)]);
        // Overpaid (imported/demo rows) and partially_paid must keep today's numbers too.
        $this->invoice(InvoiceStatus::Paid, '60.00', ['amount_paid' => '90.00']);
        $this->invoice(InvoiceStatus::PartiallyPaid, '70.00', ['amount_paid' => '20.00', 'paid_at' => Carbon::now()]);
    }

    public function test_workspace_summary_is_net_of_credits(): void
    {
        $this->seedCredited();

        $summary = app(InvoiceService::class)->workspaceSummary($this->workspace);

        $this->assertEquals(100, $summary['total_outstanding']);
        $this->assertEquals(30, $summary['total_overdue']);
        $this->assertEquals(170, $summary['paid_this_month']);
    }

    public function test_owner_dashboard_revenue_and_chart_are_net_of_credits(): void
    {
        $this->seedCredited();

        $stats = app(OwnerDashboardService::class)->statsFor($this->workspace);

        $this->assertEquals(170, $stats['revenue_this_month']);
        $this->assertEquals(170, end($stats['revenue_chart'])['amount']);
    }

    public function test_admin_dashboard_revenue_is_net_of_credits(): void
    {
        $this->seedCredited();

        $revenue = app(DashboardStatsService::class)->build()['revenue'];

        $this->assertEquals(170, $revenue['paid']);
        $this->assertEquals(100, $revenue['outstanding']);
    }

    public function test_admin_reports_are_net_of_credits(): void
    {
        $this->seedCredited();

        $report = app(ReportsService::class)->build();

        $this->assertEquals(170, $report['invoices_by_status']['paid']['amount']);
        $this->assertEquals(70, $report['invoices_by_status']['pending']['amount']);
        $this->assertEquals(30, $report['invoices_by_status']['overdue']['amount']);
        $this->assertEquals(170, end($report['revenue_by_month'])['paid']);
        $this->assertEquals(100, array_sum(array_column($report['revenue_by_month'], 'outstanding')));
        $this->assertEquals(170, $report['top_workspaces'][0]['paid_total']);
    }

    public function test_admin_workspace_detail_unpaid_totals_are_net_of_credits(): void
    {
        $this->seedCredited();

        $summary = app(AdminWorkspaceService::class)->detailFor($this->workspace)['invoices_summary'];

        $this->assertEquals(100, $summary['total_unpaid']);
        $this->assertEquals(30, $summary['total_overdue']);
    }

    public function test_member_next_invoice_shows_the_amount_net_of_credits(): void
    {
        $this->seedCredited();

        $next = app(MemberDashboardService::class)->summary($this->member)['next_invoice'];

        $this->assertEquals(30, $next['amount']);
    }

    public function test_owner_profit_and_loss_revenue_excludes_the_part_owed_back(): void
    {
        $this->seedCredited();

        $rows = app(OwnerReportsService::class)->build($this->workspace)['profit_loss'];

        $this->assertEquals(170, end($rows)['revenue']);
    }

    /**
     * Every figure is compared with the pre-credit expression, computed inline
     * (SUM(amount) over the same filters, SUM(amount_paid) for the P&L), over
     * paid, legacy paid, overpaid, partly paid, partially_paid, overdue and
     * pending invoices without any credit.
     */
    public function test_figures_without_credits_are_unchanged(): void
    {
        $this->seedPlain();

        $sum = static fn (array $statuses, string $column = 'amount'): float => (float) Invoice::query()
            ->whereIn('status', $statuses)->sum($column);
        $paid = $sum([InvoiceStatus::Paid->value]);
        $overdue = $sum([InvoiceStatus::Overdue->value]);
        $pending = $sum([InvoiceStatus::Pending->value]);
        $outstanding = $pending + $overdue;
        $oldRevenue = (float) Invoice::query()->whereNotNull('paid_at')->sum('amount_paid');
        $firstUnpaid = (float) Invoice::query()
            ->whereIn('status', [InvoiceStatus::Pending->value, InvoiceStatus::Overdue->value])
            ->orderBy('due_date')->value('amount');

        $summary = app(InvoiceService::class)->workspaceSummary($this->workspace);
        $this->assertEquals($outstanding, $summary['total_outstanding']);
        $this->assertEquals($overdue, $summary['total_overdue']);
        $this->assertEquals($paid, $summary['paid_this_month']);

        $stats = app(OwnerDashboardService::class)->statsFor($this->workspace);
        $this->assertEquals($paid, $stats['revenue_this_month']);
        $this->assertEquals($paid, end($stats['revenue_chart'])['amount']);

        $revenue = app(DashboardStatsService::class)->build()['revenue'];
        $this->assertEquals($paid, $revenue['paid']);
        $this->assertEquals($outstanding, $revenue['outstanding']);

        $report = app(ReportsService::class)->build();
        $this->assertEquals($paid, $report['invoices_by_status']['paid']['amount']);
        $this->assertEquals($pending, $report['invoices_by_status']['pending']['amount']);
        $this->assertEquals($overdue, $report['invoices_by_status']['overdue']['amount']);
        $this->assertEquals($paid, end($report['revenue_by_month'])['paid']);
        $this->assertEquals($paid, $report['top_workspaces'][0]['paid_total']);

        $detail = app(AdminWorkspaceService::class)->detailFor($this->workspace)['invoices_summary'];
        $this->assertEquals($outstanding, $detail['total_unpaid']);
        $this->assertEquals($overdue, $detail['total_overdue']);

        $this->assertEquals($firstUnpaid, app(MemberDashboardService::class)->summary($this->member)['next_invoice']['amount']);

        $pl = app(OwnerReportsService::class)->build($this->workspace)['profit_loss'];
        $this->assertEquals($oldRevenue, end($pl)['revenue']);
    }
}
