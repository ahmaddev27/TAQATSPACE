<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SeatStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Seat;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\SeatAssignedNotification;
use Illuminate\Support\Facades\DB;

/**
 * Seat lifecycle business logic for a workspace owner: create, update,
 * assign/unassign to members, and delete. Each mutation keeps the seat and
 * subscription state consistent.
 */
class SeatService
{
    /**
     * Build the seat-map payload for a workspace: every seat plus an aggregate
     * status summary. The assignee is exposed by name only.
     *
     * @return array{seats: \Illuminate\Database\Eloquent\Collection<int, Seat>, summary: array<string, int>}
     */
    public function seatMap(Workspace $workspace): array
    {
        $seats = $workspace->seats()
            ->with('assignedMember:id,name')
            ->orderBy('seat_number')
            ->get();

        $summary = [
            'total' => $seats->count(),
            'available' => $seats->where('status', SeatStatus::Available)->count(),
            'occupied' => $seats->where('status', SeatStatus::Occupied)->count(),
            'maintenance' => $seats->where('status', SeatStatus::Maintenance)->count(),
        ];

        return ['seats' => $seats, 'summary' => $summary];
    }

    /**
     * Create a seat. Enforces unique seat_number within the workspace and the
     * workspace's total_seats capacity.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(Workspace $workspace, array $data): Seat
    {
        if ($workspace->seats()->count() >= $workspace->total_seats) {
            abort(422, 'Seat capacity reached for this workspace.');
        }

        $exists = $workspace->seats()
            ->where('seat_number', $data['seat_number'])
            ->exists();

        if ($exists) {
            abort(422, 'A seat with this number already exists in the workspace.');
        }

        return $workspace->seats()->create([
            'seat_number' => $data['seat_number'],
            'type' => $data['type'],
            'status' => $data['status'] ?? SeatStatus::Available->value,
            'notes' => $data['notes'] ?? null,
        ]);
    }

    /**
     * Update mutable seat attributes (type, status, notes).
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Seat $seat, array $data): Seat
    {
        $seat->fill(array_intersect_key($data, array_flip(['type', 'status', 'notes'])));
        $seat->save();

        return $seat->refresh();
    }

    /**
     * Assign a seat to a member. The member must hold an active subscription to
     * this workspace and must not already occupy another seat.
     */
    public function assign(Seat $seat, User $member): Seat
    {
        $hasActiveSubscription = Subscription::query()
            ->where('member_id', $member->id)
            ->where('workspace_id', $seat->workspace_id)
            ->where('status', SubscriptionStatus::Active)
            ->exists();

        if (! $hasActiveSubscription) {
            abort(422, 'Member does not have an active subscription to this workspace.');
        }

        $alreadyAssigned = Seat::query()
            ->where('workspace_id', $seat->workspace_id)
            ->where('assigned_member_id', $member->id)
            ->where('id', '!=', $seat->id)
            ->exists();

        if ($alreadyAssigned) {
            abort(422, 'Member already holds a seat in this workspace.');
        }

        $seat->update([
            'status' => SeatStatus::Occupied->value,
            'assigned_member_id' => $member->id,
        ]);

        $member->notify(new SeatAssignedNotification($seat, $seat->workspace->name));

        return $seat->refresh();
    }

    /**
     * Release a seat: reset it to available and clear the assignee.
     */
    public function unassign(Seat $seat): Seat
    {
        $seat->update([
            'status' => SeatStatus::Available->value,
            'assigned_member_id' => null,
        ]);

        return $seat->refresh();
    }

    /**
     * Delete a seat. Only permitted when it is available and no subscription
     * (active or historical) references it.
     */
    public function delete(Seat $seat): void
    {
        if ($seat->status !== SeatStatus::Available) {
            abort(409, 'Only available seats can be deleted.');
        }

        $referenced = Subscription::query()
            ->where('seat_id', $seat->id)
            ->exists();

        if ($referenced) {
            abort(409, 'Seat is referenced by a subscription and cannot be deleted.');
        }

        DB::transaction(static fn () => $seat->delete());
    }
}
