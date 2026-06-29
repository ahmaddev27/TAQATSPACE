<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\InvoiceStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Admin\AdminWorkspaceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminWorkspaceServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): AdminWorkspaceService
    {
        return app(AdminWorkspaceService::class);
    }

    public function test_detail_includes_profile_and_owner(): void
    {
        $owner = User::factory()->owner()->create(['name' => 'Owner One']);
        $workspace = Workspace::factory()->create([
            'owner_id' => $owner->id,
            'name' => 'Cowork Hub',
            'city' => 'Riyadh',
        ]);

        $detail = $this->service()->detailFor($workspace);

        $this->assertSame($workspace->id, $detail['id']);
        $this->assertSame('Cowork Hub', $detail['name']);
        $this->assertSame('Riyadh', $detail['city']);
        $this->assertSame($owner->id, $detail['owner']['id']);
        $this->assertSame('Owner One', $detail['owner']['name']);
    }

    public function test_detail_lists_subscriptions_with_member(): void
    {
        $workspace = Workspace::factory()->create();
        $member = User::factory()->freelancer()->create(['name' => 'Member A']);
        Subscription::factory()->create([
            'workspace_id' => $workspace->id,
            'member_id' => $member->id,
            'status' => SubscriptionStatus::Active,
        ]);

        $detail = $this->service()->detailFor($workspace);

        $this->assertCount(1, $detail['subscriptions']);
        $this->assertSame('Member A', $detail['subscriptions'][0]['member']['name']);
        $this->assertSame(
            SubscriptionStatus::Active->value,
            $detail['subscriptions'][0]['status'],
        );
    }

    public function test_detail_invoices_and_summary_aggregate_through_subscriptions(): void
    {
        $workspace = Workspace::factory()->create();
        $sub = Subscription::factory()->create(['workspace_id' => $workspace->id]);

        Invoice::factory()->create([
            'subscription_id' => $sub->id,
            'status' => InvoiceStatus::Pending,
            'amount' => '30.00',
        ]);
        Invoice::factory()->create([
            'subscription_id' => $sub->id,
            'status' => InvoiceStatus::Overdue,
            'amount' => '20.00',
        ]);
        Invoice::factory()->create([
            'subscription_id' => $sub->id,
            'status' => InvoiceStatus::Paid,
            'amount' => '50.00',
            'paid_at' => now(),
        ]);

        $detail = $this->service()->detailFor($workspace);
        $summary = $detail['invoices_summary'];

        $this->assertCount(3, $detail['invoices']);
        $this->assertSame(3, $summary['total_count']);
        $this->assertSame(1, $summary['pending_count']);
        $this->assertSame(1, $summary['overdue_count']);
        $this->assertSame('50', (string) $summary['total_unpaid']);
        $this->assertSame('20', (string) $summary['total_overdue']);
    }

    public function test_detail_only_covers_the_given_workspace(): void
    {
        $workspace = Workspace::factory()->create();
        $other = Workspace::factory()->create();
        $otherSub = Subscription::factory()->create(['workspace_id' => $other->id]);
        Invoice::factory()->create(['subscription_id' => $otherSub->id]);

        $detail = $this->service()->detailFor($workspace);

        $this->assertSame([], $detail['subscriptions']);
        $this->assertSame([], $detail['invoices']);
        $this->assertSame(0, $detail['invoices_summary']['total_count']);
    }
}
