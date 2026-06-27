<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\SubscriptionStatus;
use App\Models\Seat;
use App\Models\Subscription;

/**
 * Keeps a seat's `assigned_member_id` and the holding subscription's `seat_id` in
 * sync, as defence-in-depth: the assign/unassign service already syncs both, but
 * this guarantees consistency for ANY code path that changes a seat's assignee
 * (booking approval, suspension, a direct update, a future feature).
 *
 * All writes use the query builder (no model events), so this never loops.
 */
class SeatObserver
{
    public function saved(Seat $seat): void
    {
        if (! $seat->wasChanged('assigned_member_id')) {
            return;
        }

        $memberId = $seat->assigned_member_id;

        if ($memberId === null) {
            // Seat freed: drop it from any subscription that still references it.
            Subscription::query()
                ->where('workspace_id', $seat->workspace_id)
                ->where('seat_id', $seat->id)
                ->update(['seat_id' => null]);

            return;
        }

        // Seat taken: no OTHER subscription may keep claiming this seat...
        Subscription::query()
            ->where('workspace_id', $seat->workspace_id)
            ->where('seat_id', $seat->id)
            ->where('member_id', '!=', $memberId)
            ->update(['seat_id' => null]);

        // ...and the holder's active subscription should point at it.
        Subscription::query()
            ->where('workspace_id', $seat->workspace_id)
            ->where('member_id', $memberId)
            ->where('status', SubscriptionStatus::Active->value)
            ->update(['seat_id' => $seat->id]);
    }
}
