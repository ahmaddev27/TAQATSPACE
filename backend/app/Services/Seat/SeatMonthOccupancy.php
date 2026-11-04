<?php

declare(strict_types=1);

namespace App\Services\Seat;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Models\Workspace;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * How many days of a month each station was held, derived from the
 * subscriptions that point at it. One query for the whole workspace.
 */
class SeatMonthOccupancy
{
    /**
     * @param  array{branch_id?: string|null, zone?: string|null}  $filters
     * @return array{month: string, days_in_month: int, seats: list<array{seat_id: string, seat_number: string, occupied_days: int, occupancy_rate: float}>}
     */
    public function forMonth(Workspace $workspace, CarbonImmutable $month, array $filters = []): array
    {
        $start = $month->startOfMonth();
        $end = $month->endOfMonth()->startOfDay();
        $daysInMonth = $start->daysInMonth;

        $seats = $workspace->seats()
            ->when($filters['branch_id'] ?? null, fn ($query, $branchId) => $query->where('branch_id', $branchId))
            ->when($filters['zone'] ?? null, fn ($query, $zone) => $query->where('zone', $zone))
            ->orderBy('seat_number')
            ->get(['id', 'seat_number']);

        $subscriptions = Subscription::query()
            ->where('workspace_id', $workspace->id)
            ->whereIn('seat_id', $seats->pluck('id'))
            ->whereIn('status', [SubscriptionStatus::Active->value, SubscriptionStatus::Expired->value])
            ->whereDate('start_date', '<=', $end->toDateString())
            ->where(fn ($query) => $query->whereNull('end_date')->orWhereDate('end_date', '>=', $start->toDateString()))
            ->get(['seat_id', 'start_date', 'end_date'])
            ->groupBy('seat_id');

        return [
            'month' => $start->format('Y-m'),
            'days_in_month' => $daysInMonth,
            'seats' => $seats->map(function ($seat) use ($subscriptions, $start, $end, $daysInMonth): array {
                $days = $this->occupiedDays($subscriptions->get($seat->id, collect()), $start, $end);

                return [
                    'seat_id' => $seat->id,
                    'seat_number' => $seat->seat_number,
                    'occupied_days' => $days,
                    'occupancy_rate' => round($days / $daysInMonth, 4),
                ];
            })->values()->all(),
        ];
    }

    /**
     * Distinct days covered by any of the subscriptions, so overlaps are not double counted.
     *
     * @param  Collection<int, Subscription>  $subscriptions
     */
    private function occupiedDays(Collection $subscriptions, CarbonImmutable $start, CarbonImmutable $end): int
    {
        $covered = [];

        foreach ($subscriptions as $subscription) {
            $from = max($start, CarbonImmutable::instance($subscription->start_date)->startOfDay());
            $to = $subscription->end_date === null
                ? $end
                : min($end, CarbonImmutable::instance($subscription->end_date)->startOfDay());

            for ($day = $from; $day <= $to; $day = $day->addDay()) {
                $covered[$day->day] = true;
            }
        }

        return count($covered);
    }
}
