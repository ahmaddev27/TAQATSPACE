<?php

declare(strict_types=1);

namespace Tests\Feature\Reviews;

use App\Models\Review;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\NewReviewNotification;
use App\Services\ReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Service-level coverage for ReviewService: eligibility, one-review-per-member,
 * average-rating recompute, owner notification, and member lookup.
 */
class ReviewServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): ReviewService
    {
        return app(ReviewService::class);
    }

    private function member(): User
    {
        return User::factory()->freelancer()->create();
    }

    // ---- memberCanReview ----

    public function test_member_with_a_subscription_can_review(): void
    {
        $member = $this->member();
        $workspace = Workspace::factory()->create();
        Subscription::factory()->create([
            'member_id' => $member->id,
            'workspace_id' => $workspace->id,
        ]);

        $this->assertTrue($this->service()->memberCanReview($member, $workspace->id));
    }

    public function test_member_without_any_subscription_cannot_review(): void
    {
        $member = $this->member();
        $workspace = Workspace::factory()->create();

        $this->assertFalse($this->service()->memberCanReview($member, $workspace->id));
    }

    public function test_a_cancelled_or_expired_subscription_still_grants_review_right(): void
    {
        $member = $this->member();
        $workspace = Workspace::factory()->create();
        Subscription::factory()->expired()->create([
            'member_id' => $member->id,
            'workspace_id' => $workspace->id,
        ]);

        $this->assertTrue($this->service()->memberCanReview($member, $workspace->id));
    }

    // ---- alreadyReviewed ----

    public function test_already_reviewed_reflects_existing_review(): void
    {
        Notification::fake();
        $member = $this->member();
        $workspace = Workspace::factory()->create();

        $this->assertFalse($this->service()->alreadyReviewed($member, $workspace->id));

        $this->service()->create($member, $workspace->id, ['rating' => 4]);

        $this->assertTrue($this->service()->alreadyReviewed($member, $workspace->id));
    }

    // ---- create + average recompute ----

    public function test_create_persists_review_and_sets_average_rating(): void
    {
        Notification::fake();
        $member = $this->member();
        $workspace = Workspace::factory()->create(['avg_rating' => 0]);

        $review = $this->service()->create($member, $workspace->id, [
            'rating' => 5,
            'comment' => 'Great place.',
        ]);

        $this->assertDatabaseHas('reviews', [
            'id' => $review->id,
            'member_id' => $member->id,
            'workspace_id' => $workspace->id,
            'rating' => 5,
            'comment' => 'Great place.',
        ]);

        $this->assertEqualsWithDelta(5.0, (float) DB::table('workspaces')->where('id', $workspace->id)->value('avg_rating'), 0.001);
    }

    public function test_average_rating_is_recomputed_across_multiple_reviews(): void
    {
        Notification::fake();
        $workspace = Workspace::factory()->create();

        $this->service()->create($this->member(), $workspace->id, ['rating' => 5]);
        $this->service()->create($this->member(), $workspace->id, ['rating' => 2]);

        // (5 + 2) / 2 = 3.50
        $this->assertEqualsWithDelta(3.5, (float) DB::table('workspaces')->where('id', $workspace->id)->value('avg_rating'), 0.001);
    }

    public function test_average_rating_is_rounded_to_two_decimals(): void
    {
        Notification::fake();
        $workspace = Workspace::factory()->create();

        // (4 + 4 + 5) / 3 = 4.333... -> 4.33
        $this->service()->create($this->member(), $workspace->id, ['rating' => 4]);
        $this->service()->create($this->member(), $workspace->id, ['rating' => 4]);
        $this->service()->create($this->member(), $workspace->id, ['rating' => 5]);

        $this->assertEqualsWithDelta(4.33, (float) DB::table('workspaces')->where('id', $workspace->id)->value('avg_rating'), 0.001);
    }

    // ---- owner notification ----

    public function test_create_notifies_the_workspace_owner(): void
    {
        Notification::fake();
        $owner = User::factory()->owner()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $member = $this->member();

        $this->service()->create($member, $workspace->id, ['rating' => 4]);

        Notification::assertSentTo($owner, NewReviewNotification::class);
    }

    // ---- forMember ----

    public function test_for_member_returns_the_members_review_or_null(): void
    {
        Notification::fake();
        $member = $this->member();
        $workspace = Workspace::factory()->create();

        $this->assertNull($this->service()->forMember($member, $workspace->id));

        $created = $this->service()->create($member, $workspace->id, ['rating' => 3]);

        $found = $this->service()->forMember($member, $workspace->id);
        $this->assertNotNull($found);
        $this->assertSame($created->id, $found->id);
    }

    // ---- paginateForWorkspace ----

    public function test_paginate_for_workspace_returns_latest_first(): void
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
        // A review on another workspace must be excluded.
        Review::factory()->create();

        $page = $this->service()->paginateForWorkspace($workspace, 15);

        $this->assertSame(2, $page->total());
        $this->assertSame($newer->id, $page->items()[0]->id);
        $this->assertSame($older->id, $page->items()[1]->id);
    }
}
