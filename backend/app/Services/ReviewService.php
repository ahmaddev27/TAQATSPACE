<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Review;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ReviewService
{
    /**
     * Whether the member currently has, or previously had, any subscription to
     * the workspace. Eligibility is independent of subscription status — a
     * cancelled/expired subscription still grants the right to review.
     */
    public function memberCanReview(User $member, string $workspaceId): bool
    {
        return $member->subscriptions()
            ->where('workspace_id', $workspaceId)
            ->exists();
    }

    public function alreadyReviewed(User $member, string $workspaceId): bool
    {
        return Review::query()
            ->where('member_id', $member->id)
            ->where('workspace_id', $workspaceId)
            ->exists();
    }

    /**
     * Persist the review and refresh the workspace's denormalized average
     * rating in a single transaction.
     *
     * @param  array{rating: int, comment?: ?string}  $data
     */
    public function create(User $member, string $workspaceId, array $data): Review
    {
        return DB::transaction(function () use ($member, $workspaceId, $data): Review {
            $review = Review::query()->create([
                'member_id' => $member->id,
                'workspace_id' => $workspaceId,
                'rating' => $data['rating'],
                'comment' => $data['comment'] ?? null,
            ]);

            $this->refreshAverageRating($workspaceId);

            return $review->load('member:id,name');
        });
    }

    /**
     * @return LengthAwarePaginator<Review>
     */
    public function paginateForWorkspace(Workspace $workspace, int $perPage = 15): LengthAwarePaginator
    {
        return $workspace->reviews()
            ->with('member:id,name')
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Recompute the denormalized `avg_rating` from the reviews table. Done via a
     * direct query so the Workspace model file stays untouched (owned elsewhere).
     */
    private function refreshAverageRating(string $workspaceId): void
    {
        $average = (float) Review::query()
            ->where('workspace_id', $workspaceId)
            ->avg('rating');

        DB::table('workspaces')
            ->where('id', $workspaceId)
            ->update(['avg_rating' => round($average, 2)]);
    }
}
