<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Enums\InvoiceStatus;
use App\Models\Expense;
use App\Models\InternetPackage;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Workspace;
use App\Services\OwnerReportsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Covers OwnerReportsService::build() — aging buckets, the trailing-6-month
 * profit-and-loss series, and internet-package uptake. All dates are expressed
 * relative to "today" so the assertions hold regardless of the run date.
 */
class OwnerReportsServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The service buckets P&L months with MySQL's DATE_FORMAT, which sqlite
        // lacks — register an equivalent UDF so build() runs end-to-end here.
        DB::connection()->getPdo()->sqliteCreateFunction(
            'DATE_FORMAT',
            static function (?string $date, string $format): ?string {
                if ($date === null) {
                    return null;
                }

                $php = str_replace(['%Y', '%m', '%d'], ['Y', 'm', 'd'], $format);

                return date($php, (int) strtotime($date));
            },
            2,
        );
    }

    private function service(): OwnerReportsService
    {
        return app(OwnerReportsService::class);
    }

    private function workspace(): Workspace
    {
        return Workspace::factory()->create();
    }

    /**
     * Create an outstanding (unpaid balance) invoice for the workspace with the
     * given due date and amounts.
     */
    private function outstandingInvoice(
        Workspace $workspace,
        Carbon $dueDate,
        string $amount,
        string $amountPaid = '0.00',
        InvoiceStatus $status = InvoiceStatus::Pending,
    ): Invoice {
        $subscription = Subscription::factory()->create(['workspace_id' => $workspace->id]);

        return Invoice::factory()->create([
            'subscription_id' => $subscription->id,
            'amount' => $amount,
            'amount_paid' => $amountPaid,
            'due_date' => $dueDate,
            'status' => $status->value,
        ]);
    }

    /**
     * Find an aging bucket by its key in the service output.
     *
     * @param  array<int, array{key: string, count: int, amount: string}>  $aging
     * @return array{key: string, count: int, amount: string}
     */
    private function bucket(array $aging, string $key): array
    {
        foreach ($aging as $row) {
            if ($row['key'] === $key) {
                return $row;
            }
        }

        $this->fail("Aging bucket [{$key}] not present.");
    }

    public function test_aging_returns_all_five_zero_filled_buckets_when_no_debt(): void
    {
        $result = $this->service()->build($this->workspace());

        $keys = array_column($result['aging'], 'key');
        $this->assertSame(['current', 'd1_30', 'd31_60', 'd61_90', 'd90_plus'], $keys);

        foreach ($result['aging'] as $row) {
            $this->assertSame(0, $row['count']);
            $this->assertSame('0.00', $row['amount']);
        }

        $this->assertSame('0.00', $result['aging_total']);
    }

    public function test_aging_places_invoices_in_the_correct_overdue_bucket(): void
    {
        $workspace = $this->workspace();
        $today = Carbon::today();

        // Not yet due -> current.
        $this->outstandingInvoice($workspace, $today->copy()->addDays(5), '100.00');
        // 10 days overdue -> d1_30.
        $this->outstandingInvoice($workspace, $today->copy()->subDays(10), '50.00', status: InvoiceStatus::Overdue);
        // 45 days overdue -> d31_60.
        $this->outstandingInvoice($workspace, $today->copy()->subDays(45), '70.00', status: InvoiceStatus::Overdue);
        // 75 days overdue -> d61_90.
        $this->outstandingInvoice($workspace, $today->copy()->subDays(75), '30.00', status: InvoiceStatus::Overdue);
        // 120 days overdue -> d90_plus.
        $this->outstandingInvoice($workspace, $today->copy()->subDays(120), '20.00', status: InvoiceStatus::Overdue);

        $aging = $this->service()->build($workspace)['aging'];

        $this->assertSame(['count' => 1, 'amount' => '100.00'], $this->only($this->bucket($aging, 'current')));
        $this->assertSame(['count' => 1, 'amount' => '50.00'], $this->only($this->bucket($aging, 'd1_30')));
        $this->assertSame(['count' => 1, 'amount' => '70.00'], $this->only($this->bucket($aging, 'd31_60')));
        $this->assertSame(['count' => 1, 'amount' => '30.00'], $this->only($this->bucket($aging, 'd61_90')));
        $this->assertSame(['count' => 1, 'amount' => '20.00'], $this->only($this->bucket($aging, 'd90_plus')));
    }

    /**
     * @param  array{key: string, count: int, amount: string}  $row
     * @return array{count: int, amount: string}
     */
    private function only(array $row): array
    {
        return ['count' => $row['count'], 'amount' => $row['amount']];
    }

    public function test_aging_uses_outstanding_balance_not_full_amount(): void
    {
        $workspace = $this->workspace();
        $today = Carbon::today();

        // Partially paid: 100 invoiced, 60 paid -> 40 outstanding in d1_30.
        $this->outstandingInvoice(
            $workspace,
            $today->copy()->subDays(15),
            '100.00',
            '60.00',
            InvoiceStatus::PartiallyPaid,
        );

        $aging = $this->service()->build($workspace)['aging'];

        $this->assertSame('40.00', $this->bucket($aging, 'd1_30')['amount']);
        $this->assertSame(1, $this->bucket($aging, 'd1_30')['count']);
    }

    public function test_aging_total_sums_outstanding_across_buckets(): void
    {
        $workspace = $this->workspace();
        $today = Carbon::today();

        $this->outstandingInvoice($workspace, $today->copy()->addDays(3), '100.00');
        $this->outstandingInvoice($workspace, $today->copy()->subDays(20), '50.00', status: InvoiceStatus::Overdue);

        $result = $this->service()->build($workspace);

        $this->assertSame('150.00', $result['aging_total']);
    }

    public function test_aging_ignores_paid_invoices_and_other_workspaces(): void
    {
        $workspace = $this->workspace();
        $other = $this->workspace();
        $today = Carbon::today();

        // Fully paid invoice -> not pending/overdue, excluded entirely.
        $paidSub = Subscription::factory()->create(['workspace_id' => $workspace->id]);
        Invoice::factory()->paid()->create([
            'subscription_id' => $paidSub->id,
            'amount' => '999.00',
            'amount_paid' => '999.00',
        ]);

        // Pending invoice belonging to another workspace.
        $this->outstandingInvoice($other, $today->copy()->subDays(10), '777.00', status: InvoiceStatus::Overdue);

        $result = $this->service()->build($workspace);

        $this->assertSame('0.00', $result['aging_total']);
        foreach ($result['aging'] as $row) {
            $this->assertSame(0, $row['count']);
        }
    }

    public function test_profit_loss_spans_six_trailing_months_ending_this_month(): void
    {
        $rows = $this->service()->build($this->workspace())['profit_loss'];

        $this->assertCount(6, $rows);

        $expectedStart = Carbon::today()->startOfMonth()->subMonths(5)->format('Y-m');
        $expectedEnd = Carbon::today()->format('Y-m');

        $this->assertSame($expectedStart, $rows[0]['month']);
        $this->assertSame($expectedEnd, $rows[5]['month']);

        foreach ($rows as $row) {
            $this->assertSame('0.00', $row['revenue']);
            $this->assertSame('0.00', $row['expenses']);
            $this->assertSame('0.00', $row['net']);
        }
    }

    public function test_profit_loss_uses_collected_payments_and_expenses_for_current_month(): void
    {
        $workspace = $this->workspace();
        $subscription = Subscription::factory()->create(['workspace_id' => $workspace->id]);

        // Collected this month: amount_paid drives revenue (not full amount).
        Invoice::factory()->create([
            'subscription_id' => $subscription->id,
            'amount' => '200.00',
            'amount_paid' => '120.00',
            'paid_at' => Carbon::now(),
            'status' => InvoiceStatus::PartiallyPaid->value,
        ]);

        Expense::factory()->create([
            'workspace_id' => $workspace->id,
            'amount' => '50.00',
            'spent_on' => Carbon::today()->toDateString(),
        ]);

        $rows = $this->service()->build($workspace)['profit_loss'];
        $current = end($rows);

        $this->assertSame('120.00', $current['revenue']);
        $this->assertSame('50.00', $current['expenses']);
        $this->assertSame('70.00', $current['net']);
    }

    public function test_profit_loss_ignores_unpaid_invoices_and_old_records(): void
    {
        $workspace = $this->workspace();
        $subscription = Subscription::factory()->create(['workspace_id' => $workspace->id]);

        // Unpaid (no paid_at) -> contributes no revenue.
        Invoice::factory()->create([
            'subscription_id' => $subscription->id,
            'amount' => '300.00',
            'amount_paid' => '0.00',
            'paid_at' => null,
            'status' => InvoiceStatus::Pending->value,
        ]);

        // Paid long before the 6-month window -> excluded.
        Invoice::factory()->create([
            'subscription_id' => $subscription->id,
            'amount' => '300.00',
            'amount_paid' => '300.00',
            'paid_at' => Carbon::today()->startOfMonth()->subMonths(8),
            'status' => InvoiceStatus::Paid->value,
        ]);

        $rows = $this->service()->build($workspace)['profit_loss'];

        foreach ($rows as $row) {
            $this->assertSame('0.00', $row['revenue']);
        }
    }

    public function test_package_uptake_counts_members_and_orders_by_popularity(): void
    {
        $workspace = $this->workspace();

        $popular = InternetPackage::factory()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Popular',
            'price' => '15.00',
        ]);
        $quiet = InternetPackage::factory()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Quiet',
            'price' => '5.00',
        ]);

        $members = User::factory()->count(3)->freelancer()->create();
        // The member_package pivot has a UUID primary key with no DB default, so
        // attach() (a raw insert, no model events) must supply the id explicitly.
        foreach ($members as $member) {
            $popular->members()->attach($member->id, ['id' => (string) Str::uuid()]);
        }
        $quiet->members()->attach($members->first()->id, ['id' => (string) Str::uuid()]);

        $uptake = $this->service()->build($workspace)['package_uptake'];

        $this->assertCount(2, $uptake);
        $this->assertSame('Popular', $uptake[0]['name']);
        $this->assertSame(3, $uptake[0]['members']);
        $this->assertSame('15.00', $uptake[0]['price']);
        $this->assertSame('Quiet', $uptake[1]['name']);
        $this->assertSame(1, $uptake[1]['members']);
    }

    public function test_package_uptake_is_scoped_to_the_workspace(): void
    {
        $workspace = $this->workspace();
        $other = $this->workspace();

        InternetPackage::factory()->create(['workspace_id' => $workspace->id, 'name' => 'Mine']);
        InternetPackage::factory()->create(['workspace_id' => $other->id, 'name' => 'Theirs']);

        $uptake = $this->service()->build($workspace)['package_uptake'];

        $this->assertCount(1, $uptake);
        $this->assertSame('Mine', $uptake[0]['name']);
        $this->assertSame(0, $uptake[0]['members']);
    }

    public function test_build_returns_every_top_level_section(): void
    {
        $result = $this->service()->build($this->workspace());

        foreach (['aging', 'aging_total', 'profit_loss', 'package_uptake'] as $key) {
            $this->assertArrayHasKey($key, $result);
        }
    }
}
