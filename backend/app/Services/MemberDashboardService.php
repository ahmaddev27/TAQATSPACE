<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\InvoiceStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\User;

class MemberDashboardService
{
    /**
     * Build the freelancer dashboard summary.
     *
     * @return array{
     *     subscription: array{status: string, workspace_name: string, plan_type: string, end_date: ?string}|null,
     *     seat: array{seat_number: string, type: string}|null,
     *     next_invoice: array{amount: string, due_date: ?string}|null,
     *     unread_notifications: int,
     *     pending_booking_requests: int
     * }
     */
    public function summary(User $member): array
    {
        $subscription = $this->activeSubscription($member);

        return [
            'subscription' => $this->subscriptionSummary($subscription),
            'seat' => $this->seatSummary($subscription),
            'next_invoice' => $this->nextInvoiceSummary($member),
            'unread_notifications' => $member->unreadNotifications()->count(),
            'pending_booking_requests' => $member->bookingRequests()
                ->where('status', BookingStatus::Pending->value)
                ->count(),
        ];
    }

    private function activeSubscription(User $member): ?Subscription
    {
        return $member->subscriptions()
            ->with(['workspace:id,name', 'seat:id,seat_number,type'])
            ->where('status', SubscriptionStatus::Active->value)
            ->latest('start_date')
            ->first();
    }

    /**
     * @return array{status: string, workspace_name: string, plan_type: string, end_date: ?string}|null
     */
    private function subscriptionSummary(?Subscription $subscription): ?array
    {
        if ($subscription === null) {
            return null;
        }

        return [
            'status' => $subscription->status->value,
            'workspace_name' => (string) $subscription->workspace?->name,
            'plan_type' => $subscription->plan_type->value,
            'end_date' => $subscription->end_date?->toDateString(),
        ];
    }

    /**
     * @return array{seat_number: string, type: string}|null
     */
    private function seatSummary(?Subscription $subscription): ?array
    {
        $seat = $subscription?->seat;

        if ($seat === null) {
            return null;
        }

        return [
            'seat_number' => $seat->seat_number,
            'type' => $seat->type->value,
        ];
    }

    /**
     * The soonest unpaid (pending or overdue) invoice across the member's
     * subscriptions, ordered by due date.
     *
     * @return array{amount: string, due_date: ?string}|null
     */
    private function nextInvoiceSummary(User $member): ?array
    {
        $invoice = Invoice::query()
            ->whereHas('subscription', static fn ($query) => $query->where('member_id', $member->id))
            ->whereIn('status', [InvoiceStatus::Pending->value, InvoiceStatus::Overdue->value])
            ->orderBy('due_date')
            ->first(['id', 'amount', 'due_date']);

        if ($invoice === null) {
            return null;
        }

        return [
            'amount' => (string) $invoice->amount,
            'due_date' => $invoice->due_date?->toDateString(),
        ];
    }
}
