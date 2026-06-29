<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\InvoiceStatus;
use App\Enums\WorkspaceStatus;
use App\Models\BookingRequest;
use App\Models\Invoice;
use App\Models\User;
use App\Models\Workspace;

/**
 * Computes the "needs your action" counters surfaced as red badges on the
 * dashboard sidebar. Every counter is a single indexed COUNT query (no N+1),
 * scoped to what the caller is allowed to act on:
 *
 *  - Owner:  pending booking requests + under-review receipts in THEIR workspace.
 *  - Admin:  workspaces awaiting publish/approval (status = pending), platform-wide.
 *  - Others: nothing to approve, so an empty payload.
 *
 * Only the keys relevant to the caller's role are returned; a zero is a valid
 * value (the frontend simply hides a badge whose count is 0).
 */
class NavActionCountService
{
    /**
     * @return array<string, int>
     */
    public function forUser(User $user): array
    {
        if ($user->isAdmin()) {
            return $this->adminCounts();
        }

        if ($user->isOwner()) {
            return $this->ownerCounts($user);
        }

        // Freelancers (and any other role) submit but never approve/reject.
        return [];
    }

    /**
     * Platform-wide count of workspaces awaiting review. A workspace is created
     * with status `pending` and stays there until an admin approves (active) or
     * rejects it — so `pending` is the admin's approve/reject queue.
     *
     * @return array{workspaces: int}
     */
    private function adminCounts(): array
    {
        return [
            'workspaces' => Workspace::query()
                ->where('status', WorkspaceStatus::Pending->value)
                ->count(),
        ];
    }

    /**
     * Owner-scoped queues: pending booking requests and invoices whose uploaded
     * receipt is under review (awaiting approve/reject). Limited to the owner's
     * own workspace; a zeroed payload when they have no workspace yet.
     *
     * @return array{bookings: int, receipts: int}
     */
    private function ownerCounts(User $user): array
    {
        $workspace = $user->workspace;

        if ($workspace === null) {
            return ['bookings' => 0, 'receipts' => 0];
        }

        return [
            'bookings' => $this->pendingBookings($workspace),
            'receipts' => $this->receiptsUnderReview($workspace),
        ];
    }

    private function pendingBookings(Workspace $workspace): int
    {
        return BookingRequest::query()
            ->where('workspace_id', $workspace->id)
            ->where('status', BookingStatus::Pending->value)
            ->count();
    }

    private function receiptsUnderReview(Workspace $workspace): int
    {
        return Invoice::query()
            ->join('subscriptions', 'subscriptions.id', '=', 'invoices.subscription_id')
            ->where('subscriptions.workspace_id', $workspace->id)
            ->where('invoices.status', InvoiceStatus::UnderReview->value)
            ->count();
    }
}
