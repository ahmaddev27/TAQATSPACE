<?php

declare(strict_types=1);

namespace Tests\Feature\Nav;

use App\Enums\BookingStatus;
use App\Enums\InvoiceStatus;
use App\Enums\WorkspaceStatus;
use App\Models\BookingRequest;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NavActionCountTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/nav/action-counts')->assertUnauthorized();
    }

    public function test_owner_sees_pending_bookings_and_under_review_receipts_scoped_to_their_workspace(): void
    {
        $owner = User::factory()->owner()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);

        // Action queue for this owner.
        BookingRequest::factory()->count(2)->create([
            'workspace_id' => $workspace->id,
            'status' => BookingStatus::Pending,
        ]);
        BookingRequest::factory()->create([
            'workspace_id' => $workspace->id,
            'status' => BookingStatus::Approved,
        ]);

        $subscription = Subscription::factory()->create(['workspace_id' => $workspace->id]);
        Invoice::factory()->count(3)->create([
            'subscription_id' => $subscription->id,
            'status' => InvoiceStatus::UnderReview,
        ]);
        Invoice::factory()->create([
            'subscription_id' => $subscription->id,
            'status' => InvoiceStatus::Paid,
        ]);

        // Noise from another workspace must not leak into this owner's counts.
        $otherWorkspace = Workspace::factory()->create();
        BookingRequest::factory()->create([
            'workspace_id' => $otherWorkspace->id,
            'status' => BookingStatus::Pending,
        ]);
        $otherSubscription = Subscription::factory()->create(['workspace_id' => $otherWorkspace->id]);
        Invoice::factory()->create([
            'subscription_id' => $otherSubscription->id,
            'status' => InvoiceStatus::UnderReview,
        ]);

        Sanctum::actingAs($owner);

        $this->getJson('/api/nav/action-counts')
            ->assertOk()
            ->assertJsonPath('data.bookings', 2)
            ->assertJsonPath('data.receipts', 3);
    }

    public function test_owner_without_workspace_gets_zeroes(): void
    {
        $owner = User::factory()->owner()->create();

        Sanctum::actingAs($owner);

        $this->getJson('/api/nav/action-counts')
            ->assertOk()
            ->assertJsonPath('data.bookings', 0)
            ->assertJsonPath('data.receipts', 0);
    }

    public function test_admin_sees_platform_wide_pending_workspaces_count(): void
    {
        $admin = User::factory()->admin()->create();

        Workspace::factory()->count(2)->create(['status' => WorkspaceStatus::Pending]);
        Workspace::factory()->create(['status' => WorkspaceStatus::Active]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/nav/action-counts')
            ->assertOk()
            ->assertJsonPath('data.workspaces', 2);
    }

    public function test_freelancer_gets_empty_payload(): void
    {
        $freelancer = User::factory()->freelancer()->create();

        Sanctum::actingAs($freelancer);

        $this->getJson('/api/nav/action-counts')
            ->assertOk()
            ->assertExactJson(['data' => []]);
    }
}
