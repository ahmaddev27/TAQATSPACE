<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\PlanType;
use App\Enums\SeatStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\WorkspaceStatus;
use App\Models\BookingRequest;
use App\Models\Seat;
use App\Models\SeatTypePrice;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\BookingApprovedNotification;
use App\Notifications\BookingRejectedNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Booking request lifecycle: a freelancer submits a request, an owner approves
 * (creating a subscription + assigning a seat in one transaction) or rejects it.
 */
class BookingService
{
    /**
     * Submit a booking request on behalf of a freelancer.
     *
     * Guards: the workspace must be active, the freelancer may hold at most one
     * pending request overall, and may not already have an active subscription
     * to the target workspace.
     *
     * @param  array<string, mixed>  $data
     */
    public function submit(User $member, array $data): BookingRequest
    {
        $workspace = Workspace::query()->findOrFail($data['workspace_id']);

        if ($workspace->status !== WorkspaceStatus::Active) {
            abort(422, 'This workspace is not accepting booking requests.');
        }

        $hasPending = BookingRequest::query()
            ->where('member_id', $member->id)
            ->where('status', BookingStatus::Pending)
            ->exists();

        if ($hasPending) {
            abort(422, 'You already have a pending booking request.');
        }

        $hasActiveSubscription = Subscription::query()
            ->where('member_id', $member->id)
            ->where('workspace_id', $workspace->id)
            ->where('status', SubscriptionStatus::Active)
            ->exists();

        if ($hasActiveSubscription) {
            abort(422, 'You already have an active subscription to this workspace.');
        }

        return $workspace->bookingRequests()->create([
            'member_id' => $member->id,
            'preferred_seat_type' => $data['preferred_seat_type'] ?? null,
            'message' => $data['message'] ?? null,
            'status' => BookingStatus::Pending->value,
        ]);

        // NOTE: notifying the owner of a new request (T036's NewBookingRequest)
        // is owned by the notifications module; this service intentionally does
        // not fabricate that class to stay within its module boundary.
    }

    /**
     * Approve a pending booking request inside a transaction.
     *
     * Re-checks seat availability under a row lock to defend against a race
     * where the seat was taken between the owner loading the UI and approving.
     */
    public function approve(BookingRequest $booking, User $reviewer, ?string $seatId): BookingRequest
    {
        $this->assertPending($booking);

        return DB::transaction(function () use ($booking, $reviewer, $seatId): BookingRequest {
            $seat = null;

            if ($seatId !== null) {
                $seat = Seat::query()
                    ->where('id', $seatId)
                    ->where('workspace_id', $booking->workspace_id)
                    ->lockForUpdate()
                    ->first();

                if ($seat === null) {
                    abort(422, 'The selected seat does not belong to this workspace.');
                }

                if ($seat->status !== SeatStatus::Available) {
                    abort(409, 'The selected seat is no longer available. Please choose another seat.');
                }
            }

            $workspace = $booking->workspace;

            $subscription = Subscription::query()->create([
                'member_id' => $booking->member_id,
                'workspace_id' => $booking->workspace_id,
                'seat_id' => $seat?->id,
                'plan_type' => PlanType::Monthly->value,
                'start_date' => Carbon::today()->toDateString(),
                'monthly_price' => $this->resolveMonthlyPrice($booking, $workspace),
                'status' => SubscriptionStatus::Active->value,
            ]);

            if ($seat !== null) {
                $seat->update([
                    'status' => SeatStatus::Occupied->value,
                    'assigned_member_id' => $booking->member_id,
                ]);
            }

            $booking->update([
                'status' => BookingStatus::Approved->value,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
            ]);

            $booking->member->notify(new BookingApprovedNotification($booking, $workspace->name));

            return $booking->refresh()->load('member');
        });
    }

    /**
     * Reject a pending booking request.
     */
    public function reject(BookingRequest $booking, User $reviewer, ?string $reason): BookingRequest
    {
        $this->assertPending($booking);

        $booking->update([
            'status' => BookingStatus::Rejected->value,
            'reviewed_by' => $reviewer->id,
            'rejection_reason' => $reason,
            'reviewed_at' => now(),
        ]);

        $booking->member->notify(new BookingRejectedNotification($booking, $booking->workspace->name));

        return $booking->refresh()->load('member');
    }

    /**
     * Cancel an active subscription on behalf of its member.
     *
     * Marks the subscription cancelled, frees the assigned seat, and leaves any
     * unpaid invoices intact as outstanding obligations.
     */
    public function cancelSubscription(Subscription $subscription): Subscription
    {
        if ($subscription->status === SubscriptionStatus::Cancelled) {
            abort(409, 'This subscription is already cancelled.');
        }

        return DB::transaction(function () use ($subscription): Subscription {
            $seat = $subscription->seat;

            if ($seat !== null) {
                $seat->update([
                    'status' => SeatStatus::Available->value,
                    'assigned_member_id' => null,
                ]);
            }

            $subscription->update([
                'status' => SubscriptionStatus::Cancelled->value,
                'cancelled_at' => now(),
            ]);

            return $subscription->refresh();
        });
    }

    private function assertPending(BookingRequest $booking): void
    {
        if ($booking->status !== BookingStatus::Pending) {
            abort(409, 'This booking request has already been reviewed.');
        }
    }

    /**
     * Resolve the subscription's monthly price from the requested seat type's
     * pricing, falling back to the workspace base price when the type has no
     * monthly price configured (or no preferred type was requested).
     */
    private function resolveMonthlyPrice(BookingRequest $booking, Workspace $workspace): string
    {
        $seatType = $booking->preferred_seat_type;

        if ($seatType !== null) {
            $price = SeatTypePrice::query()
                ->where('workspace_id', $workspace->id)
                ->where('type', $seatType->value)
                ->value('price_monthly');

            if ($price !== null) {
                return (string) $price;
            }
        }

        return (string) $workspace->price_per_month;
    }
}
