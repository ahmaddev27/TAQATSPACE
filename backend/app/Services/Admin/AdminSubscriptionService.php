<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Read-only super-admin subscription tracking. The admin observes status and
 * billing terms across the platform; mutations live with the owner module.
 */
class AdminSubscriptionService
{
    /**
     * Filtered, paginated subscriptions with member + workspace eager-loaded.
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Subscription>
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        return $this->filteredQuery($filters)
            ->with(['member:id,name,email', 'workspace:id,name,city'])
            ->latest()
            ->paginate($this->perPage($filters))
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Subscription>
     */
    private function filteredQuery(array $filters): Builder
    {
        $query = Subscription::query();

        if (! empty($filters['status']) && SubscriptionStatus::tryFrom((string) $filters['status']) !== null) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['workspace_id'])) {
            $query->where('workspace_id', $filters['workspace_id']);
        }

        if (! empty($filters['member'])) {
            $like = '%'.trim((string) $filters['member']).'%';

            $query->whereHas('member', function (Builder $member) use ($like): void {
                $member->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like);
            });
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function perPage(array $filters): int
    {
        $perPage = (int) ($filters['per_page'] ?? 15);

        return max(1, min($perPage, 100));
    }
}
