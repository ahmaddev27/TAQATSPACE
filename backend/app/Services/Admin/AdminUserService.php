<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Super-admin user administration: a filtered, paginated directory plus a
 * single status transition (activate a pending owner, suspend an account).
 */
class AdminUserService
{
    /**
     * Filtered, paginated user directory.
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, User>
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        return $this->filteredQuery($filters)
            ->latest()
            ->paginate($this->perPage($filters))
            ->withQueryString();
    }

    /**
     * Transition a user's account status (admin moderation).
     */
    public function changeStatus(User $user, UserStatus $status): User
    {
        $user->forceFill(['status' => $status->value])->save();

        return $user->refresh();
    }

    /**
     * Filtered query (no pagination), newest first, for memory-safe
     * cursor-based CSV export of the full result set.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<User>
     */
    public function exportQuery(array $filters): Builder
    {
        return $this->filteredQuery($filters)->latest();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<User>
     */
    private function filteredQuery(array $filters): Builder
    {
        $query = User::query();

        if (! empty($filters['role']) && UserRole::tryFrom((string) $filters['role']) !== null) {
            $query->where('role', $filters['role']);
        }

        if (! empty($filters['status']) && UserStatus::tryFrom((string) $filters['status']) !== null) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['search'])) {
            $like = '%'.trim((string) $filters['search']).'%';

            $query->where(function (Builder $inner) use ($like): void {
                $inner->where('name', 'like', $like)
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

        return max(1, min($perPage, 200));
    }
}
