<?php

declare(strict_types=1);

namespace Tests\Feature\Subscriptions;

use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * HTTP coverage for the member subscription endpoints: listing the member's
 * own subscriptions (newest first) and showing a single one with invoices,
 * with ownership enforced.
 */
class MemberSubscriptionEndpointTest extends TestCase
{
    use RefreshDatabase;

    private function member(): User
    {
        return User::factory()->freelancer()->create();
    }

    // ---- index ----

    public function test_guest_cannot_list_member_subscriptions(): void
    {
        $this->getJson('/api/member/subscriptions')->assertUnauthorized();
    }

    public function test_owner_role_is_forbidden_from_the_member_endpoint(): void
    {
        Sanctum::actingAs(User::factory()->owner()->create());

        $this->getJson('/api/member/subscriptions')->assertForbidden();
    }

    public function test_member_sees_only_their_own_subscriptions(): void
    {
        $member = $this->member();
        $workspace = Workspace::factory()->create();

        Subscription::factory()->count(2)->create([
            'member_id' => $member->id,
            'workspace_id' => $workspace->id,
        ]);
        // Another member's subscription must be excluded.
        Subscription::factory()->create();

        Sanctum::actingAs($member);

        $this->getJson('/api/member/subscriptions')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure(['data' => [['id', 'workspace_id', 'status', 'plan_type']]]);
    }

    public function test_member_subscriptions_are_returned_newest_first(): void
    {
        $member = $this->member();

        $older = Subscription::factory()->create([
            'member_id' => $member->id,
            'created_at' => now()->subDays(5),
        ]);
        $newer = Subscription::factory()->create([
            'member_id' => $member->id,
            'created_at' => now(),
        ]);

        Sanctum::actingAs($member);

        $this->getJson('/api/member/subscriptions')
            ->assertOk()
            ->assertJsonPath('data.0.id', $newer->id)
            ->assertJsonPath('data.1.id', $older->id);
    }

    // ---- show ----

    public function test_member_views_one_of_their_subscriptions_with_invoices(): void
    {
        $member = $this->member();
        $subscription = Subscription::factory()->create(['member_id' => $member->id]);
        Invoice::factory()->create(['subscription_id' => $subscription->id]);

        Sanctum::actingAs($member);

        $this->getJson("/api/member/subscriptions/{$subscription->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $subscription->id)
            ->assertJsonCount(1, 'data.invoices');
    }

    public function test_member_cannot_view_a_subscription_owned_by_another_member(): void
    {
        $member = $this->member();
        $foreign = Subscription::factory()->create();

        Sanctum::actingAs($member);

        $this->getJson("/api/member/subscriptions/{$foreign->id}")
            ->assertForbidden();
    }
}
