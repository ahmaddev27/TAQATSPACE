<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\InvoiceStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Enums\WorkspaceStatus;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Admin\ReportsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportsServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The service uses MySQL DATE_FORMAT for its month buckets. Register an
        // equivalent UDF on the in-memory sqlite connection so the production
        // query runs unchanged under test (only the '%Y-%m' bucket is used).
        $pdo = \DB::connection()->getPdo();
        $pdo->sqliteCreateFunction('DATE_FORMAT', static function (?string $date, string $format): ?string {
            if ($date === null) {
                return null;
            }

            return date(strtr($format, ['%Y' => 'Y', '%m' => 'm', '%d' => 'd']), strtotime($date));
        });
    }

    private function service(): ReportsService
    {
        return app(ReportsService::class);
    }

    public function test_invoices_by_status_returns_count_and_summed_amount(): void
    {
        Invoice::factory()->create(['status' => InvoiceStatus::Paid, 'amount' => '100.00']);
        Invoice::factory()->create(['status' => InvoiceStatus::Paid, 'amount' => '25.50']);
        Invoice::factory()->create(['status' => InvoiceStatus::Pending, 'amount' => '40.00']);

        $section = $this->service()->build()['invoices_by_status'];

        $this->assertSame(2, $section['paid']['count']);
        $this->assertSame('125.50', $section['paid']['amount']);
        $this->assertSame(1, $section['pending']['count']);
        $this->assertSame('40.00', $section['pending']['amount']);
        // Overdue bucket always present, zero-filled.
        $this->assertSame(0, $section['overdue']['count']);
        $this->assertSame('0.00', $section['overdue']['amount']);
    }

    public function test_subscriptions_by_status_is_zero_filled_across_every_status(): void
    {
        Subscription::factory()->count(2)->create(['status' => SubscriptionStatus::Active]);
        Subscription::factory()->expired()->create();

        $section = $this->service()->build()['subscriptions_by_status'];

        foreach (SubscriptionStatus::cases() as $status) {
            $this->assertArrayHasKey($status->value, $section);
        }
        $this->assertSame(2, $section[SubscriptionStatus::Active->value]);
        $this->assertSame(1, $section[SubscriptionStatus::Expired->value]);
        $this->assertSame(0, $section[SubscriptionStatus::Cancelled->value]);
    }

    public function test_top_workspaces_ranks_by_paid_invoice_total(): void
    {
        $high = Workspace::factory()->create(['name' => 'High Revenue']);
        $low = Workspace::factory()->create(['name' => 'Low Revenue']);

        $highSub = Subscription::factory()->create([
            'workspace_id' => $high->id,
            'status' => SubscriptionStatus::Active,
        ]);
        $lowSub = Subscription::factory()->create([
            'workspace_id' => $low->id,
            'status' => SubscriptionStatus::Active,
        ]);

        Invoice::factory()->create([
            'subscription_id' => $highSub->id,
            'status' => InvoiceStatus::Paid,
            'amount' => '200.00',
        ]);
        Invoice::factory()->create([
            'subscription_id' => $lowSub->id,
            'status' => InvoiceStatus::Paid,
            'amount' => '50.00',
        ]);
        // Pending invoice must not count toward the paid total.
        Invoice::factory()->create([
            'subscription_id' => $lowSub->id,
            'status' => InvoiceStatus::Pending,
            'amount' => '999.00',
        ]);

        $top = $this->service()->build()['top_workspaces'];

        $this->assertCount(2, $top);
        $this->assertSame('High Revenue', $top[0]['name']);
        $this->assertSame('200.00', $top[0]['paid_total']);
        $this->assertSame(1, $top[0]['active_subscriptions']);
        $this->assertSame('Low Revenue', $top[1]['name']);
        $this->assertSame('50.00', $top[1]['paid_total']);
    }

    public function test_top_workspaces_is_empty_without_paid_invoices(): void
    {
        $workspace = Workspace::factory()->create();
        $sub = Subscription::factory()->create(['workspace_id' => $workspace->id]);
        Invoice::factory()->create([
            'subscription_id' => $sub->id,
            'status' => InvoiceStatus::Pending,
        ]);

        $this->assertSame([], $this->service()->build()['top_workspaces']);
    }

    public function test_workspaces_by_status_is_zero_filled(): void
    {
        Workspace::factory()->count(2)->create(['status' => WorkspaceStatus::Active]);
        Workspace::factory()->pending()->create();

        $section = $this->service()->build()['workspaces_by_status'];

        foreach (WorkspaceStatus::cases() as $status) {
            $this->assertArrayHasKey($status->value, $section);
        }
        $this->assertSame(2, $section[WorkspaceStatus::Active->value]);
        $this->assertSame(1, $section[WorkspaceStatus::Pending->value]);
        $this->assertSame(0, $section[WorkspaceStatus::Suspended->value]);
    }

    public function test_users_by_role_is_zero_filled(): void
    {
        User::factory()->count(2)->freelancer()->create();
        User::factory()->owner()->create();

        $section = $this->service()->build()['users_by_role'];

        foreach (UserRole::cases() as $role) {
            $this->assertArrayHasKey($role->value, $section);
        }
        $this->assertSame(2, $section[UserRole::Freelancer->value]);
        $this->assertSame(1, $section[UserRole::WorkspaceOwner->value]);
    }

    public function test_revenue_by_month_is_a_contiguous_12_month_series(): void
    {
        Invoice::factory()->create([
            'status' => InvoiceStatus::Paid,
            'amount' => '120.00',
            'paid_at' => now(),
        ]);

        $series = $this->service()->build()['revenue_by_month'];

        $this->assertCount(12, $series);
        $current = now()->format('Y-m');
        $last = $series[11];
        $this->assertSame($current, $last['month']);
        $this->assertSame('120.00', $last['paid']);
    }

    public function test_subscriptions_by_month_is_a_contiguous_12_month_series(): void
    {
        $series = $this->service()->build()['subscriptions_by_month'];

        $this->assertCount(12, $series);
        $this->assertSame(now()->format('Y-m'), $series[11]['month']);
    }

    public function test_build_returns_every_top_level_section(): void
    {
        $result = $this->service()->build();

        foreach ([
            'revenue_by_month',
            'invoices_by_status',
            'subscriptions_by_status',
            'subscriptions_by_month',
            'top_workspaces',
            'workspaces_by_status',
            'users_by_role',
        ] as $key) {
            $this->assertArrayHasKey($key, $result);
        }
    }
}
