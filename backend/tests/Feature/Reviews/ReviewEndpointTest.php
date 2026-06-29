<?php

declare(strict_types=1);

namespace Tests\Feature\Reviews;

use App\Models\Review;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * HTTP coverage for the Review endpoints: public per-workspace listing,
 * authenticated freelancer submission (eligibility + one-per-member), and
 * the "mine" lookup.
 */
class ReviewEndpointTest extends TestCase
{
    use RefreshDatabase;

    private function member(): User
    {
        return User::factory()->freelancer()->create();
    }

    private function subscribedMember(Workspace $workspace): User
    {
        $member = $this->member();
        Subscription::factory()->create([
            'member_id' => $member->id,
            'workspace_id' => $workspace->id,
        ]);

        return $member;
    }

    // ---- store ----

    public function test_guest_cannot_submit_a_review(): void
    {
        $workspace = Workspace::factory()->create();

        $this->postJson('/api/reviews', [
            'workspace_id' => $workspace->id,
            'rating' => 5,
        ])->assertUnauthorized();
    }

    public function test_owner_role_is_forbidden_from_submitting(): void
    {
        $workspace = Workspace::factory()->create();
        Sanctum::actingAs(User::factory()->owner()->create());

        $this->postJson('/api/reviews', [
            'workspace_id' => $workspace->id,
            'rating' => 5,
        ])->assertForbidden();
    }

    public function test_subscribed_member_can_submit_a_review(): void
    {
        Notification::fake();
        $workspace = Workspace::factory()->create();
        $member = $this->subscribedMember($workspace);

        Sanctum::actingAs($member);

        $this->postJson('/api/reviews', [
            'workspace_id' => $workspace->id,
            'rating' => 5,
            'comment' => 'Excellent.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.rating', 5)
            ->assertJsonPath('data.comment', 'Excellent.')
            ->assertJsonStructure(['data' => ['id', 'reviewer_name', 'rating', 'comment', 'created_at']]);

        $this->assertDatabaseHas('reviews', [
            'member_id' => $member->id,
            'workspace_id' => $workspace->id,
            'rating' => 5,
        ]);
    }

    public function test_member_without_subscription_cannot_review(): void
    {
        $workspace = Workspace::factory()->create();
        Sanctum::actingAs($this->member());

        $this->postJson('/api/reviews', [
            'workspace_id' => $workspace->id,
            'rating' => 4,
        ])->assertForbidden();
    }

    public function test_member_cannot_review_the_same_workspace_twice(): void
    {
        Notification::fake();
        $workspace = Workspace::factory()->create();
        $member = $this->subscribedMember($workspace);
        Review::factory()->create([
            'member_id' => $member->id,
            'workspace_id' => $workspace->id,
        ]);

        Sanctum::actingAs($member);

        $this->postJson('/api/reviews', [
            'workspace_id' => $workspace->id,
            'rating' => 3,
        ])->assertStatus(409);
    }

    public function test_store_validates_rating_range(): void
    {
        $workspace = Workspace::factory()->create();
        Sanctum::actingAs($this->subscribedMember($workspace));

        $this->postJson('/api/reviews', [
            'workspace_id' => $workspace->id,
            'rating' => 6,
        ])->assertStatus(422)->assertJsonValidationErrors('rating');
    }

    public function test_store_validates_workspace_exists(): void
    {
        Sanctum::actingAs($this->member());

        $this->postJson('/api/reviews', [
            'workspace_id' => 'non-existent',
            'rating' => 4,
        ])->assertStatus(422)->assertJsonValidationErrors('workspace_id');
    }

    public function test_reviewer_name_exposes_only_first_name(): void
    {
        Notification::fake();
        $workspace = Workspace::factory()->create();
        $member = $this->subscribedMember($workspace);
        $member->update(['name' => 'Ahmad Qaddora']);

        Sanctum::actingAs($member);

        $this->postJson('/api/reviews', [
            'workspace_id' => $workspace->id,
            'rating' => 5,
        ])
            ->assertCreated()
            ->assertJsonPath('data.reviewer_name', 'Ahmad');
    }

    // ---- mine ----

    public function test_mine_returns_null_when_member_has_not_reviewed(): void
    {
        $workspace = Workspace::factory()->create();
        Sanctum::actingAs($this->member());

        $this->getJson('/api/reviews/mine?workspace_id='.$workspace->id)
            ->assertOk()
            ->assertJsonPath('data.review', null);
    }

    public function test_mine_returns_the_members_own_review(): void
    {
        $workspace = Workspace::factory()->create();
        $member = $this->member();
        $review = Review::factory()->create([
            'member_id' => $member->id,
            'workspace_id' => $workspace->id,
        ]);

        Sanctum::actingAs($member);

        $this->getJson('/api/reviews/mine?workspace_id='.$workspace->id)
            ->assertOk()
            ->assertJsonPath('data.review.id', $review->id);
    }

    public function test_mine_requires_authentication(): void
    {
        $this->getJson('/api/reviews/mine?workspace_id=x')->assertUnauthorized();
    }

    // ---- index ----

    public function test_index_lists_reviews_for_a_workspace_publicly(): void
    {
        $workspace = Workspace::factory()->create();
        Review::factory()->count(3)->create(['workspace_id' => $workspace->id]);
        Review::factory()->create(); // another workspace, must be excluded

        $this->getJson("/api/workspaces/{$workspace->id}/reviews")
            ->assertOk()
            ->assertJsonCount(3, 'data.reviews')
            ->assertJsonStructure([
                'data' => [
                    'reviews' => [['id', 'reviewer_name', 'rating', 'comment', 'created_at']],
                    'meta' => ['current_page', 'last_page', 'per_page', 'total'],
                ],
            ])
            ->assertJsonPath('data.meta.total', 3);
    }

    public function test_index_returns_latest_first(): void
    {
        $workspace = Workspace::factory()->create();
        $older = Review::factory()->create([
            'workspace_id' => $workspace->id,
            'created_at' => now()->subDay(),
        ]);
        $newer = Review::factory()->create([
            'workspace_id' => $workspace->id,
            'created_at' => now(),
        ]);

        $this->getJson("/api/workspaces/{$workspace->id}/reviews")
            ->assertOk()
            ->assertJsonPath('data.reviews.0.id', $newer->id)
            ->assertJsonPath('data.reviews.1.id', $older->id);
    }

    public function test_index_clamps_per_page_within_bounds(): void
    {
        $workspace = Workspace::factory()->create();
        Review::factory()->count(2)->create(['workspace_id' => $workspace->id]);

        $this->getJson("/api/workspaces/{$workspace->id}/reviews?per_page=999")
            ->assertOk()
            ->assertJsonPath('data.meta.per_page', 50);
    }
}
