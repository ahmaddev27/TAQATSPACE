<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Enums\SubscriptionStatus;
use App\Models\BookingRequest;
use App\Models\SeatTypePrice;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Workspace;
use App\Support\MediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * Full single-user payload for the super-admin detail view. Carries the common
 * profile header plus a role-branched bundle: a freelancer's subscriptions and
 * recent bookings, or an owner's workspace(s) and verification documents.
 *
 * The role-specific relations are eager-loaded by the service before this
 * resource is built, so no queries are issued here.
 *
 * @mixin User
 */
class AdminUserDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return array_merge(
            $this->profile(),
            $this->roleBundle(),
        );
    }

    /**
     * Common profile header shared by every role.
     *
     * @return array<string, mixed>
     */
    private function profile(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'role' => $this->role->value,
            'status' => $this->status->value,
            'specialty' => $this->specialty,
            'bio' => $this->bio,
            'avatar' => MediaUrl::resolve($this->avatar),
            'email_verified_at' => $this->email_verified_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /**
     * The role-specific section of the payload. Exactly one of the keys is
     * populated; the others stay absent so the client can branch on `role`.
     *
     * @return array<string, mixed>
     */
    private function roleBundle(): array
    {
        if ($this->resource->isOwner()) {
            return [
                'workspaces' => $this->workspaces(),
                'documents' => $this->documents(),
            ];
        }

        if ($this->resource->isFreelancer()) {
            return [
                'subscriptions' => $this->subscriptions(),
                'subscriptions_summary' => $this->subscriptionsSummary(),
                'recent_bookings' => $this->recentBookings(),
            ];
        }

        return [];
    }

    /**
     * The owner's workspace(s) with the headline figures an admin needs.
     *
     * @return array<int, array<string, mixed>>
     */
    private function workspaces(): array
    {
        return $this->whenLoaded('workspace', function (): array {
            $workspace = $this->resource->workspace;

            if ($workspace === null) {
                return [];
            }

            return [$this->workspaceRow($workspace)];
        }, []);
    }

    /**
     * @return array<string, mixed>
     */
    private function workspaceRow(Workspace $workspace): array
    {
        return [
            'id' => $workspace->id,
            'name' => $workspace->name,
            'status' => $workspace->status->value,
            'city' => $workspace->city,
            'total_seats' => $workspace->total_seats,
            'price_per_month' => $workspace->price_per_month,
            'avg_rating' => $workspace->avg_rating,
            'created_at' => $workspace->created_at?->toIso8601String(),
            'seat_types' => $this->seatTypes($workspace),
            'seats' => $this->seatAvailability($workspace),
        ];
    }

    /**
     * The workspace's configured seat-type pricing tiers.
     *
     * @return array<int, array<string, mixed>>
     */
    private function seatTypes(Workspace $workspace): array
    {
        if (! $workspace->relationLoaded('seatTypes')) {
            return [];
        }

        return $workspace->seatTypes
            ->map(fn (SeatTypePrice $seatType): array => [
                'id' => $seatType->id,
                'type' => $seatType->type->value,
                'monthly_price' => $seatType->price_monthly,
                'daily_price' => $seatType->price_daily,
                'capacity' => $seatType->capacity,
                'enabled' => $seatType->enabled,
            ])
            ->all();
    }

    /**
     * Seat availability for the workspace. `occupied` is the live count of
     * occupied seats (eager-loaded as `occupied_seats_count` by the service);
     * `available` is the headline capacity minus those occupied seats.
     *
     * @return array<string, int>
     */
    private function seatAvailability(Workspace $workspace): array
    {
        $total = (int) $workspace->total_seats;
        $occupied = (int) ($workspace->occupied_seats_count ?? 0);

        return [
            'total' => $total,
            'occupied' => $occupied,
            'available' => max(0, $total - $occupied),
        ];
    }

    /**
     * Owner verification documents (license / id) resolved to viewable URLs.
     * Returns null when none were uploaded.
     *
     * @return array<string, string|null>|null
     */
    private function documents(): ?array
    {
        /** @var array<string, mixed>|null $documents */
        $documents = $this->resource->documents;

        if (empty($documents)) {
            return null;
        }

        // Registration stores the license under `license_file`; older/seed rows
        // use `license`. Accept either so existing accounts still resolve.
        $license = $documents['license_file'] ?? $documents['license'] ?? null;
        $idDocument = $documents['id_document'] ?? null;

        return [
            'license_file' => is_string($license) ? MediaUrl::resolve($license) : null,
            'id_document' => is_string($idDocument) ? MediaUrl::resolve($idDocument) : null,
        ];
    }

    /**
     * The freelancer's subscriptions, each enriched with the workspace name and
     * a derived `is_active` flag (status === active and not past the end date).
     *
     * @return array<int, array<string, mixed>>
     */
    private function subscriptions(): array
    {
        return $this->whenLoaded('subscriptions', fn (): array => $this->resource->subscriptions
            ->map(fn (Subscription $subscription): array => $this->subscriptionRow($subscription))
            ->all(), []);
    }

    /**
     * @return array<string, mixed>
     */
    private function subscriptionRow(Subscription $subscription): array
    {
        return [
            'id' => $subscription->id,
            'plan_type' => $subscription->plan_type->value,
            'monthly_price' => $subscription->monthly_price,
            'status' => $subscription->status->value,
            'start_date' => $subscription->start_date?->toDateString(),
            'end_date' => $subscription->end_date?->toDateString(),
            'cancelled_at' => $subscription->cancelled_at?->toIso8601String(),
            'created_at' => $subscription->created_at?->toIso8601String(),
            'is_active' => $this->subscriptionIsActive($subscription),
            'lifecycle' => $this->subscriptionLifecycle($subscription),
            'seat_number' => $subscription->relationLoaded('seat') && $subscription->seat !== null
                ? $subscription->seat->seat_number
                : null,
            'workspace' => $subscription->relationLoaded('workspace') && $subscription->workspace !== null
                ? [
                    'id' => $subscription->workspace->id,
                    'name' => $subscription->workspace->name,
                    'city' => $subscription->workspace->city,
                ]
                : null,
        ];
    }

    /**
     * A subscription is effectively active when its status is Active and the end
     * date (if any) has not passed.
     */
    private function subscriptionIsActive(Subscription $subscription): bool
    {
        if ($subscription->status !== SubscriptionStatus::Active) {
            return false;
        }

        $endDate = $subscription->end_date;

        return $endDate === null || $endDate->endOfDay()->greaterThanOrEqualTo(Carbon::now());
    }

    /**
     * A single lifecycle label the UI can badge directly, derived from status
     * and dates:
     *  - cancelled / suspended / expired follow the stored status verbatim;
     *  - an Active subscription whose start date is in the future is `upcoming`;
     *  - an Active subscription past its end date is `expired`;
     *  - otherwise `active`.
     */
    private function subscriptionLifecycle(Subscription $subscription): string
    {
        if ($subscription->status === SubscriptionStatus::Cancelled) {
            return 'cancelled';
        }

        if ($subscription->status === SubscriptionStatus::Suspended) {
            return 'suspended';
        }

        if ($subscription->status === SubscriptionStatus::Expired) {
            return 'expired';
        }

        $now = Carbon::now();
        $startDate = $subscription->start_date;

        if ($startDate !== null && $startDate->startOfDay()->greaterThan($now)) {
            return 'upcoming';
        }

        $endDate = $subscription->end_date;

        if ($endDate !== null && $endDate->endOfDay()->lessThan($now)) {
            return 'expired';
        }

        return 'active';
    }

    /**
     * Counts the freelancer needs at a glance, bucketed by lifecycle so the
     * admin sees the spread (active vs. expired vs. cancelled) at one look.
     *
     * @return array<string, int>
     */
    private function subscriptionsSummary(): array
    {
        return $this->whenLoaded('subscriptions', function (): array {
            $subscriptions = $this->resource->subscriptions;

            $byLifecycle = $subscriptions
                ->groupBy(fn (Subscription $subscription): string => $this->subscriptionLifecycle($subscription))
                ->map(fn ($group): int => $group->count());

            return [
                'total' => $subscriptions->count(),
                'active' => (int) $byLifecycle->get('active', 0),
                'upcoming' => (int) $byLifecycle->get('upcoming', 0),
                'expired' => (int) $byLifecycle->get('expired', 0),
                'cancelled' => (int) $byLifecycle->get('cancelled', 0),
            ];
        }, ['total' => 0, 'active' => 0, 'upcoming' => 0, 'expired' => 0, 'cancelled' => 0]);
    }

    /**
     * A few of the freelancer's most recent booking requests for context.
     *
     * @return array<int, array<string, mixed>>
     */
    private function recentBookings(): array
    {
        return $this->whenLoaded('bookingRequests', fn (): array => $this->resource->bookingRequests
            ->map(fn (BookingRequest $booking): array => [
                'id' => $booking->id,
                'status' => $booking->status->value,
                'workspace' => $booking->relationLoaded('workspace') && $booking->workspace !== null
                    ? ['id' => $booking->workspace->id, 'name' => $booking->workspace->name]
                    : null,
                'created_at' => $booking->created_at?->toIso8601String(),
            ])
            ->all(), []);
    }
}
