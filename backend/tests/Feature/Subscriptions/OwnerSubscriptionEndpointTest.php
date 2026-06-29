<?php

declare(strict_types=1);

namespace Tests\Feature\Subscriptions;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * HTTP coverage for the owner-facing subscription endpoints: roster listing,
 * renew, cancel and internet provisioning — focusing on auth, ownership
 * scoping and the JSON envelope. Provisioning internals live in InternetAccessTest.
 */
class OwnerSubscriptionEndpointTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        return User::factory()->owner()->create();
    }

    private function workspaceFor(User $owner): Workspace
    {
        return Workspace::factory()->create(['owner_id' => $owner->id]);
    }

    // ---- index ----

    public function test_guest_cannot_list_subscriptions(): void
    {
        $this->getJson('/api/workspace/subscriptions')->assertUnauthorized();
    }

    public function test_freelancer_is_forbidden_from_the_owner_roster(): void
    {
        Sanctum::actingAs(User::factory()->freelancer()->create());

        $this->getJson('/api/workspace/subscriptions')->assertForbidden();
    }

    public function test_owner_lists_only_their_own_workspace_subscriptions(): void
    {
        $owner = $this->owner();
        $workspace = $this->workspaceFor($owner);

        Subscription::factory()->count(2)->create(['workspace_id' => $workspace->id]);
        // Foreign subscription in another workspace must never appear.
        Subscription::factory()->create();

        Sanctum::actingAs($owner);

        $this->getJson('/api/workspace/subscriptions')
            ->assertOk()
            ->assertJsonCount(2, 'data.subscriptions')
            ->assertJsonPath('data.meta.total', 2)
            ->assertJsonStructure([
                'data' => [
                    'subscriptions' => [['id', 'member' => ['id', 'name'], 'status', 'total_price']],
                    'meta' => ['current_page', 'per_page', 'total', 'last_page'],
                ],
            ]);
    }

    public function test_owner_without_a_workspace_gets_an_empty_roster(): void
    {
        Sanctum::actingAs($this->owner());

        $this->getJson('/api/workspace/subscriptions')
            ->assertOk()
            ->assertJsonCount(0, 'data.subscriptions')
            ->assertJsonPath('data.meta.total', 0);
    }

    public function test_status_filter_is_applied_through_the_endpoint(): void
    {
        $owner = $this->owner();
        $workspace = $this->workspaceFor($owner);
        Subscription::factory()->create([
            'workspace_id' => $workspace->id,
            'status' => SubscriptionStatus::Active->value,
        ]);
        Subscription::factory()->cancelled()->create(['workspace_id' => $workspace->id]);

        Sanctum::actingAs($owner);

        $this->getJson('/api/workspace/subscriptions?status=cancelled')
            ->assertOk()
            ->assertJsonCount(1, 'data.subscriptions')
            ->assertJsonPath('data.subscriptions.0.status', 'cancelled');
    }

    public function test_invalid_filter_is_rejected_with_validation_error(): void
    {
        Sanctum::actingAs($this->owner());

        $this->getJson('/api/workspace/subscriptions?status=bogus')
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');
    }

    // ---- renew ----

    public function test_owner_renews_a_due_subscription(): void
    {
        $owner = $this->owner();
        $workspace = $this->workspaceFor($owner);
        $subscription = Subscription::factory()->create([
            'workspace_id' => $workspace->id,
            'status' => SubscriptionStatus::Expired->value,
            'end_date' => Carbon::today()->subDay()->toDateString(),
        ]);

        Sanctum::actingAs($owner);

        $this->putJson("/api/workspace/subscriptions/{$subscription->id}/renew")
            ->assertOk()
            ->assertJsonPath('data.status', SubscriptionStatus::Active->value);
    }

    public function test_renewing_a_term_not_yet_due_returns_422(): void
    {
        $owner = $this->owner();
        $workspace = $this->workspaceFor($owner);
        $subscription = Subscription::factory()->create([
            'workspace_id' => $workspace->id,
            'status' => SubscriptionStatus::Active->value,
            'end_date' => Carbon::today()->addMonth()->toDateString(),
        ]);

        Sanctum::actingAs($owner);

        $this->putJson("/api/workspace/subscriptions/{$subscription->id}/renew")
            ->assertStatus(422);
    }

    public function test_owner_cannot_renew_a_foreign_subscription(): void
    {
        $owner = $this->owner();
        $this->workspaceFor($owner);
        $foreign = Subscription::factory()->create([
            'status' => SubscriptionStatus::Expired->value,
            'end_date' => Carbon::today()->subDay()->toDateString(),
        ]);

        Sanctum::actingAs($owner);

        // Cross-workspace renewal is blocked by the service guard (422).
        $this->putJson("/api/workspace/subscriptions/{$foreign->id}/renew")
            ->assertStatus(422);

        $this->assertSame(
            SubscriptionStatus::Expired,
            $foreign->refresh()->status,
        );
    }

    // ---- cancel ----

    public function test_owner_cancels_their_subscription(): void
    {
        $owner = $this->owner();
        $workspace = $this->workspaceFor($owner);
        $subscription = Subscription::factory()->create([
            'workspace_id' => $workspace->id,
            'status' => SubscriptionStatus::Active->value,
        ]);

        Sanctum::actingAs($owner);

        $this->putJson("/api/workspace/subscriptions/{$subscription->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', SubscriptionStatus::Cancelled->value);

        $this->assertSame(SubscriptionStatus::Cancelled, $subscription->refresh()->status);
    }

    public function test_owner_cannot_cancel_a_foreign_subscription(): void
    {
        $owner = $this->owner();
        $this->workspaceFor($owner);
        $foreign = Subscription::factory()->create([
            'status' => SubscriptionStatus::Active->value,
        ]);

        Sanctum::actingAs($owner);

        $this->putJson("/api/workspace/subscriptions/{$foreign->id}/cancel")
            ->assertStatus(422);

        $this->assertSame(SubscriptionStatus::Active, $foreign->refresh()->status);
    }

    // ---- provisionInternet (endpoint auth/ownership only) ----

    public function test_owner_provisions_internet_for_their_subscription(): void
    {
        Bus::fake();
        $owner = $this->owner();
        $workspace = $this->workspaceFor($owner);
        $subscription = Subscription::factory()->create([
            'workspace_id' => $workspace->id,
            'status' => SubscriptionStatus::Active->value,
        ]);

        Sanctum::actingAs($owner);

        $this->postJson("/api/workspace/subscriptions/{$subscription->id}/internet")
            ->assertOk()
            ->assertJsonStructure([
                'data' => ['username', 'password', 'sent_email', 'sent_sms'],
                'message',
            ]);

        $this->assertNotNull($subscription->refresh()->internet_provisioned_at);
    }

    public function test_owner_cannot_provision_internet_for_a_foreign_subscription(): void
    {
        Bus::fake();
        $owner = $this->owner();
        $this->workspaceFor($owner);
        $foreign = Subscription::factory()->create([
            'status' => SubscriptionStatus::Active->value,
        ]);

        Sanctum::actingAs($owner);

        $this->postJson("/api/workspace/subscriptions/{$foreign->id}/internet")
            ->assertForbidden();

        $this->assertNull($foreign->refresh()->internet_provisioned_at);
    }

    public function test_owner_without_a_workspace_cannot_provision_internet(): void
    {
        Bus::fake();
        $owner = $this->owner();
        $subscription = Subscription::factory()->create([
            'status' => SubscriptionStatus::Active->value,
        ]);

        Sanctum::actingAs($owner);

        $this->postJson("/api/workspace/subscriptions/{$subscription->id}/internet")
            ->assertForbidden();
    }

    public function test_freelancer_cannot_reach_the_owner_internet_endpoint(): void
    {
        Bus::fake();
        $subscription = Subscription::factory()->create();

        Sanctum::actingAs(User::factory()->freelancer()->create());

        $this->postJson("/api/workspace/subscriptions/{$subscription->id}/internet")
            ->assertForbidden();
    }
}
