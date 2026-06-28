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
use App\Notifications\NewBookingRequestNotification;
use App\Services\Partner\WebhookDispatcher;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Booking request lifecycle: a freelancer submits a request, an owner approves
 * (creating a subscription + assigning a seat in one transaction) or rejects it.
 */
class BookingService
{
    public function __construct(private readonly WebhookDispatcher $webhooks) {}

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
            abort(422, __('messages.booking_not_accepting'));
        }

        $hasPending = BookingRequest::query()
            ->where('member_id', $member->id)
            ->where('status', BookingStatus::Pending)
            ->exists();

        if ($hasPending) {
            abort(422, __('messages.booking_already_pending'));
        }

        $hasActiveSubscription = Subscription::query()
            ->where('member_id', $member->id)
            ->where('workspace_id', $workspace->id)
            ->where('status', SubscriptionStatus::Active)
            ->exists();

        if ($hasActiveSubscription) {
            abort(422, __('messages.booking_already_subscribed'));
        }

        $bookingRequest = $workspace->bookingRequests()->create([
            'member_id' => $member->id,
            'preferred_seat_type' => $data['preferred_seat_type'] ?? null,
            'plan_type' => $data['plan_type'] ?? PlanType::Monthly->value,
            'message' => $data['message'] ?? null,
            'status' => BookingStatus::Pending->value,
        ]);

        // Tell the workspace owner so they can review the request promptly.
        $workspace->owner?->notify(new NewBookingRequestNotification(
            $bookingRequest,
            $member->name,
            $workspace->name,
        ));

        return $bookingRequest;
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

        $subscription = null;

        $approved = DB::transaction(function () use ($booking, $reviewer, $seatId, &$subscription): BookingRequest {
            $seat = null;

            if ($seatId !== null) {
                $seat = Seat::query()
                    ->where('id', $seatId)
                    ->where('workspace_id', $booking->workspace_id)
                    ->lockForUpdate()
                    ->first();

                if ($seat === null) {
                    abort(422, __('messages.seat_not_in_workspace'));
                }

                if ($seat->status !== SeatStatus::Available) {
                    abort(409, __('messages.seat_unavailable'));
                }

                // The assigned seat must match the type the freelancer requested
                // (when they specified one), so an owner can't place them in a
                // different seat class than they asked for.
                if (
                    $booking->preferred_seat_type !== null
                    && $seat->type !== $booking->preferred_seat_type
                ) {
                    abort(422, __('messages.seat_type_mismatch'));
                }
            }

            $workspace = $booking->workspace;

            // Term + price follow the plan the freelancer chose: a daily plan runs
            // one day, a monthly plan one month. Set the expiry up front so the
            // member (and renewal/expiry reminders) have a real end date.
            $planType = $booking->plan_type ?? PlanType::Monthly;
            $startDate = Carbon::today();
            $endDate = $planType === PlanType::Daily
                ? $startDate->copy()->addDay()
                : $startDate->copy()->addMonth();

            $subscription = Subscription::query()->create([
                'member_id' => $booking->member_id,
                'workspace_id' => $booking->workspace_id,
                'seat_id' => $seat?->id,
                'plan_type' => $planType->value,
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'monthly_price' => $this->resolvePlanPrice($booking, $workspace, $planType),
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

            return $booking->refresh()->load('member', 'workspace');
        });

        // Notify integrated partners (e.g. Academy) after the subscription is
        // committed, so they only ever see a booking that truly landed.
        $this->webhooks->bookingApproved($approved, $subscription);

        return $approved;
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

        $rejected = $booking->refresh()->load('member', 'workspace');

        $this->webhooks->bookingRejected($rejected);

        return $rejected;
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
            abort(409, __('messages.subscription_already_cancelled'));
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
            abort(409, __('messages.booking_already_reviewed'));
        }
    }

    /**
     * Resolve the subscription's price for the chosen plan from the requested
     * seat type's pricing — the daily price for a daily plan, the monthly price
     * otherwise — falling back to the workspace base price when the type has no
     * matching price configured (or no preferred type was requested).
     */
    private function resolvePlanPrice(BookingRequest $booking, Workspace $workspace, PlanType $planType): string
    {
        $seatType = $booking->preferred_seat_type;
        $column = $planType === PlanType::Daily ? 'price_daily' : 'price_monthly';

        if ($seatType !== null) {
            $price = SeatTypePrice::query()
                ->where('workspace_id', $workspace->id)
                ->where('type', $seatType->value)
                ->value($column);

            if ($price !== null) {
                return (string) $price;
            }
        }

        return (string) $workspace->price_per_month;
    }
}
