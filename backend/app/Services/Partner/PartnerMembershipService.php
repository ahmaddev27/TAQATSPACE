<?php

declare(strict_types=1);

namespace App\Services\Partner;

use App\Enums\BookingStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Models\BookingRequest;
use App\Models\User;

/**
 * Resolves a student's membership state on Work for a partner platform.
 *
 * Students are matched by their shared SSO subject (`sso_sub`) first, then by
 * email, so Academy can look them up by whichever identifier it holds.
 */
class PartnerMembershipService
{
    /**
     * @return array{
     *     exists: bool,
     *     freelancer_active: bool,
     *     subscriptions: list<array<string, mixed>>,
     *     pending_booking: array<string, mixed>|null
     * }
     */
    public function forIdentifier(string $identifier): array
    {
        $user = $this->resolveUser($identifier);

        if ($user === null) {
            return [
                'exists' => false,
                'freelancer_active' => false,
                'subscriptions' => [],
                'pending_booking' => null,
            ];
        }

        $subscriptions = $user->subscriptions()
            ->with('workspace:id,name')
            ->get()
            ->map(fn ($subscription): array => [
                'workspace_id' => $subscription->workspace_id,
                'workspace_name' => $subscription->workspace?->name,
                'status' => $subscription->status->value,
                'start_date' => $subscription->start_date?->toDateString(),
                'end_date' => $subscription->end_date?->toDateString(),
            ])
            ->all();

        $pending = $user->bookingRequests()
            ->where('status', BookingStatus::Pending)
            ->latest()
            ->first();

        return [
            'exists' => true,
            'freelancer_active' => $user->role === UserRole::Freelancer && $user->isActive(),
            'subscriptions' => $subscriptions,
            'pending_booking' => $pending === null ? null : [
                'workspace_id' => $pending->workspace_id,
                'status' => $pending->status->value,
            ],
        ];
    }

    private function resolveUser(string $identifier): ?User
    {
        return User::query()
            ->where('sso_sub', $identifier)
            ->orWhere('email', $identifier)
            ->first();
    }
}
