<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\InvoiceStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\WorkspaceStatus;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Admin\DashboardStatsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardStatsServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): DashboardStatsService
    {
        return app(DashboardStatsService::class);
    }

    public function test_workspace_counters_are_bucketed_by_status(): void
    {
        Workspace::factory()->count(2)->create(['status' => WorkspaceStatus::Active]);
        Workspace::factory()->pending()->create();
        Workspace::factory()->suspended()->create();

        $stats = $this->service()->build()['workspaces'];

        $this->assertSame(2, $stats['active']);
        $this->assertSame(1, $stats['pending']);
        $this->assertSame(1, $stats['suspended']);
        $this->assertSame(4, $stats['total']);
    }

    public function test_users_total_excludes_staff_admins(): void
    {
        User::factory()->count(3)->freelancer()->create();
        User::factory()->count(2)->owner()->create();
        User::factory()->admin()->create();
        User::factory()->unverified()->freelancer()->create();

        $stats = $this->service()->build()['users'];

        $this->assertSame(4, $stats['freelancers']);
        $this->assertSame(2, $stats['owners']);
        $this->assertSame(1, $stats['admins']);
        // Total = freelancers + owners only (admins excluded).
        $this->assertSame(6, $stats['total']);
        $this->assertSame(1, $stats['pending']);
    }

    public function test_subscription_counters(): void
    {
        Subscription::factory()->count(2)->create(['status' => SubscriptionStatus::Active]);
        Subscription::factory()->expired()->create();

        $stats = $this->service()->build()['subscriptions'];

        $this->assertSame(2, $stats['active']);
        $this->assertSame(3, $stats['total']);
    }

    public function test_invoice_counters_and_revenue_totals(): void
    {
        Invoice::factory()->create(['status' => InvoiceStatus::Paid, 'amount' => '100.00']);
        Invoice::factory()->create(['status' => InvoiceStatus::Paid, 'amount' => '50.00']);
        Invoice::factory()->create(['status' => InvoiceStatus::Pending, 'amount' => '30.00']);
        Invoice::factory()->create(['status' => InvoiceStatus::Overdue, 'amount' => '20.00']);

        $result = $this->service()->build();

        $this->assertSame(2, $result['invoices']['paid']);
        $this->assertSame(1, $result['invoices']['pending']);
        $this->assertSame(1, $result['invoices']['overdue']);
        $this->assertSame(4, $result['invoices']['total']);

        $this->assertSame('150.00', $result['revenue']['paid']);
        $this->assertSame('50.00', $result['revenue']['outstanding']);
    }

    public function test_empty_database_yields_zeroed_payload(): void
    {
        $result = $this->service()->build();

        $this->assertSame(0, $result['workspaces']['total']);
        $this->assertSame(0, $result['users']['total']);
        $this->assertSame('0.00', $result['revenue']['paid']);
        $this->assertSame('0.00', $result['revenue']['outstanding']);
    }
}
