<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SeatStatus;
use App\Enums\SeatType;
use App\Enums\SubscriptionStatus;
use App\Models\Seat;
use App\Models\SeatTypePrice;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\SeatAssignedNotification;
use Illuminate\Database\Eloquent\Collection;
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

    /** Per-type seat-number prefix so seats stay readable & classifiable. */
    private const TYPE_PREFIX = [
        'flexible' => 'F',
        'fixed' => 'X',
        'private_office' => 'P',
    ];

    /**
     * Reconcile the physical Seat rows to the enabled seat types' capacities,
     * making seat types the single driver of how many seats exist and of what
     * type. Idempotent: saving the same capacities twice changes nothing.
     *
     * Rules, applied per type inside one transaction:
     *   - Enabled type: ensure exactly `capacity` seats of that type. Missing
     *     seats are created with auto numbers (prefix + zero-padded index);
     *     surplus seats are removed but ONLY when available (never occupied,
     *     reserved, in maintenance, or referenced by a subscription).
     *   - Disabled type (or a type with no row): all of its available seats are
     *     removed; occupied/reserved ones are kept so live members are untouched.
     *
     * When occupied seats block a capacity decrease, the live seat count may
     * stay above the configured capacity — mirroring the existing total_seats
     * ceiling behaviour rather than evicting members.
     */
    public function syncSeatsToCapacity(Workspace $workspace): void
    {
        DB::transaction(function () use ($workspace): void {
            /** @var Collection<int, SeatTypePrice> $seatTypes */
            $seatTypes = $workspace->seatTypes()->get()->keyBy(
                static fn (SeatTypePrice $row): string => $row->type->value,
            );

            /** @var Collection<int, Seat> $seats */
            $seats = $workspace->seats()->lockForUpdate()->get();
            $byType = $seats->groupBy(static fn (Seat $seat): string => $seat->type->value);

            foreach (SeatType::cases() as $type) {
                $existing = $byType->get($type->value, new Collection);
                $row = $seatTypes->get($type->value);
                $target = ($row !== null && $row->enabled) ? max(0, (int) $row->capacity) : 0;

                $this->reconcileType($workspace, $type, $existing, $target);
            }
        });
    }

    /**
     * Bring one seat type to its target count: create missing seats or prune
     * removable surplus. Never deletes a seat that is not safely removable.
     *
     * @param  Collection<int, Seat>  $existing
     */
    private function reconcileType(Workspace $workspace, SeatType $type, Collection $existing, int $target): void
    {
        $current = $existing->count();

        if ($current < $target) {
            $this->createSeats($workspace, $type, $target - $current);

            return;
        }

        if ($current > $target) {
            $this->pruneSeats($existing, $current - $target);
        }
    }

    /**
     * Create `count` new seats of the given type with unique auto numbers that
     * continue the per-type sequence (e.g. F-01, F-02 …) without colliding with
     * any existing seat number in the workspace.
     */
    private function createSeats(Workspace $workspace, SeatType $type, int $count): void
    {
        $prefix = self::TYPE_PREFIX[$type->value] ?? strtoupper(substr($type->value, 0, 1));

        $taken = $workspace->seats()
            ->pluck('seat_number')
            ->flip();

        $index = 0;
        $created = 0;

        while ($created < $count) {
            $index++;
            $number = sprintf('%s-%02d', $prefix, $index);

            if ($taken->has($number)) {
                continue;
            }

            $workspace->seats()->create([
                'seat_number' => $number,
                'type' => $type->value,
                'status' => SeatStatus::Available->value,
            ]);

            $taken->put($number, true);
            $created++;
        }
    }

    /**
     * Remove up to `count` surplus seats of a type, deleting only seats that are
     * safe to drop: available status and not referenced by any subscription.
     *
     * @param  Collection<int, Seat>  $existing
     */
    private function pruneSeats(Collection $existing, int $count): void
    {
        $removable = $existing
            ->filter(static fn (Seat $seat): bool => $seat->status === SeatStatus::Available)
            ->sortByDesc('seat_number')
            ->values();

        $removed = 0;

        foreach ($removable as $seat) {
            if ($removed >= $count) {
                break;
            }

            $referenced = Subscription::query()
                ->where('seat_id', $seat->id)
                ->exists();

            if ($referenced) {
                continue;
            }

            $seat->delete();
            $removed++;
        }
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
            abort(422, __('messages.seat_capacity_reached'));
        }

        $exists = $workspace->seats()
            ->where('seat_number', $data['seat_number'])
            ->exists();

        if ($exists) {
            abort(422, __('messages.seat_number_exists'));
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
            abort(422, __('messages.seat_member_no_subscription'));
        }

        $alreadyAssigned = Seat::query()
            ->where('workspace_id', $seat->workspace_id)
            ->where('assigned_member_id', $member->id)
            ->where('id', '!=', $seat->id)
            ->exists();

        if ($alreadyAssigned) {
            abort(422, __('messages.seat_member_already_holds'));
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
            abort(409, __('messages.seat_only_available_deletable'));
        }

        $referenced = Subscription::query()
            ->where('seat_id', $seat->id)
            ->exists();

        if ($referenced) {
            abort(409, __('messages.seat_referenced_by_subscription'));
        }

        DB::transaction(static fn () => $seat->delete());
    }
}
