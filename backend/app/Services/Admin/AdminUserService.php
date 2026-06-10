<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Enums\SeatStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use App\Notifications\AccountStatusChangedNotification;
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
     * Load a single user with the relations its role needs for the detail view.
     *
     * A freelancer gets their subscriptions (with each workspace) and a handful
     * of recent bookings; an owner gets their workspace. Relations are eager-
     * loaded here so the resource issues no further queries (no N+1).
     */
    public function findForDetail(User $user): User
    {
        if ($user->isFreelancer()) {
            $user->load([
                'subscriptions' => fn ($query) => $query->latest(),
                'subscriptions.workspace:id,name,city',
                'subscriptions.seat:id,seat_number',
                'bookingRequests' => fn ($query) => $query->latest()->limit(5),
                'bookingRequests.workspace:id,name',
            ]);
        }

        if ($user->isOwner()) {
            // The owner's workspace plus everything the detail view renders:
            // seat-type pricing and a live count of occupied seats (for the
            // availability stat). withCount keeps the count a single query, and
            // loading seatTypes alongside it avoids a per-row N+1.
            $user->load([
                'workspace' => fn ($query) => $query
                    ->with('seatTypes')
                    ->withCount([
                        'seats as occupied_seats_count' => fn (Builder $seats) => $seats
                            ->where('status', SeatStatus::Occupied->value),
                    ]),
            ]);
        }

        return $user;
    }

    /**
     * Transition a user's account status (admin moderation).
     */
    public function changeStatus(User $user, UserStatus $status): User
    {
        $user->forceFill(['status' => $status->value])->save();

        // Tell the user when they are suspended or reactivated — never silently.
        if (in_array($status, [UserStatus::Suspended, UserStatus::Active], true)) {
            $user->notify(new AccountStatusChangedNotification($status));
        }

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
        // The user directory manages platform members only — staff (admins) are
        // administered in the admin-management module, never here.
        $query = User::query()->whereIn('role', [
            UserRole::Freelancer->value,
            UserRole::WorkspaceOwner->value,
        ]);

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
