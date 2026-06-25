<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\Gender;
use App\Enums\InvoiceStatus;
use App\Enums\SeatStatus;
use App\Enums\SubscriptionStatus;
use App\Models\BookingRequest;
use App\Models\Invoice;
use App\Models\Seat;
use App\Models\Subscription;
use App\Models\Workspace;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class OwnerDashboardService
{
    private const REVENUE_MONTHS = 6;

    /**
     * Build the owner's dashboard snapshot.
     *
     * Computed fresh on every load (a handful of cheap aggregate queries): the
     * counts must reflect the live state immediately — a previous 5-minute cache
     * showed stale figures (e.g. a just-cancelled subscription still counted as
     * an active member, or a new pending request still reading 0).
     *
     * Returns a zeroed payload when the owner has no workspace yet, so the
     * caller can always respond 200 with a predictable shape.
     *
     * @return array<string, mixed>
     */
    public function statsFor(?Workspace $workspace): array
    {
        if ($workspace === null) {
            return $this->emptyStats();
        }

        return $this->buildStats($workspace);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildStats(Workspace $workspace): array
    {
        $now = Carbon::now();

        $totalSeats = $this->totalSeats($workspace);
        $availableSeats = $this->availableSeats($workspace);
        $occupiedSeats = $totalSeats - $availableSeats;

        return [
            'occupancy_pct' => $totalSeats > 0
                ? round(($occupiedSeats / $totalSeats) * 100, 1)
                : 0.0,
            'active_members' => $this->activeMembers($workspace),
            'available_seats' => $availableSeats,
            'pending_bookings' => $this->pendingBookings($workspace),
            'overdue_invoices' => $this->overdueInvoices($workspace),
            'revenue_this_month' => $this->revenueForMonth($workspace, $now),
            'revenue_last_month' => $this->revenueForMonth($workspace, $now->copy()->subMonthNoOverflow()),
            'revenue_chart' => $this->revenueChart($workspace, $now),
            'members_by_gender' => $this->membersByGender($workspace),
            'members_by_status' => $this->membersByStatus($workspace),
        ];
    }

    /**
     * This workspace's active members bucketed by gender (male / female /
     * unspecified), zero-filled. One grouped query over active subscriptions
     * joined to their member; null gender collapses to "unspecified".
     *
     * @return array<int, array{label: string, value: int}>
     */
    private function membersByGender(Workspace $workspace): array
    {
        $counts = Subscription::query()
            ->join('users', 'users.id', '=', 'subscriptions.member_id')
            ->where('subscriptions.workspace_id', $workspace->id)
            ->where('subscriptions.status', SubscriptionStatus::Active->value)
            ->selectRaw('users.gender as bucket, COUNT(*) as aggregate')
            ->groupBy('users.gender')
            ->pluck('aggregate', 'bucket')
            ->mapWithKeys(fn (int|string $count, int|string|null $gender): array => [
                ($gender === null || $gender === '') ? 'unspecified' : (string) $gender => (int) $count,
            ]);

        return $this->genderBuckets($counts);
    }

    /**
     * This workspace's members bucketed by subscription status, zero-filled
     * across every status. One grouped query.
     *
     * @return array<int, array{label: string, value: int}>
     */
    private function membersByStatus(Workspace $workspace): array
    {
        $counts = Subscription::query()
            ->where('workspace_id', $workspace->id)
            ->selectRaw('status as bucket, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'bucket');

        return array_map(
            static fn (SubscriptionStatus $status): array => [
                'label' => $status->value,
                'value' => (int) $counts->get($status->value, 0),
            ],
            SubscriptionStatus::cases(),
        );
    }

    /**
     * Shape gender counts into the contiguous, zero-filled `[{label, value}]`
     * series (male, female, unspecified).
     *
     * @param  Collection<string, int>  $counts
     * @return array<int, array{label: string, value: int}>
     */
    private function genderBuckets(Collection $counts): array
    {
        $buckets = [Gender::Male->value, Gender::Female->value, 'unspecified'];

        return array_map(
            static fn (string $bucket): array => [
                'label' => $bucket,
                'value' => (int) $counts->get($bucket, 0),
            ],
            $buckets,
        );
    }

    private function totalSeats(Workspace $workspace): int
    {
        return Seat::query()
            ->where('workspace_id', $workspace->id)
            ->count();
    }

    private function availableSeats(Workspace $workspace): int
    {
        return Seat::query()
            ->where('workspace_id', $workspace->id)
            ->where('status', SeatStatus::Available->value)
            ->count();
    }

    private function activeMembers(Workspace $workspace): int
    {
        return Subscription::query()
            ->where('workspace_id', $workspace->id)
            ->where('status', SubscriptionStatus::Active->value)
            ->count();
    }

    private function pendingBookings(Workspace $workspace): int
    {
        return BookingRequest::query()
            ->where('workspace_id', $workspace->id)
            ->where('status', BookingStatus::Pending->value)
            ->count();
    }

    private function overdueInvoices(Workspace $workspace): int
    {
        return $this->workspaceInvoices($workspace)
            ->where('invoices.status', InvoiceStatus::Overdue->value)
            ->count();
    }

    private function revenueForMonth(Workspace $workspace, Carbon $month): float
    {
        return (float) $this->workspaceInvoices($workspace)
            ->where('invoices.status', InvoiceStatus::Paid->value)
            ->whereYear('invoices.paid_at', $month->year)
            ->whereMonth('invoices.paid_at', $month->month)
            ->sum('invoices.amount');
    }

    /**
     * Last N months of paid revenue, oldest first, zero-filled for gaps.
     *
     * @return array<int, array{month: string, amount: float}>
     */
    private function revenueChart(Workspace $workspace, Carbon $now): array
    {
        $start = $now->copy()->startOfMonth()->subMonthsNoOverflow(self::REVENUE_MONTHS - 1);

        $totals = $this->workspaceInvoices($workspace)
            ->where('invoices.status', InvoiceStatus::Paid->value)
            ->where('invoices.paid_at', '>=', $start)
            ->selectRaw("DATE_FORMAT(invoices.paid_at, '%Y-%m') as ym, SUM(invoices.amount) as total")
            ->groupBy('ym')
            ->pluck('total', 'ym');

        $chart = [];

        for ($i = 0; $i < self::REVENUE_MONTHS; $i++) {
            $month = $start->copy()->addMonthsNoOverflow($i);
            $key = $month->format('Y-m');

            $chart[] = [
                'month' => $key,
                'amount' => (float) ($totals[$key] ?? 0),
            ];
        }

        return $chart;
    }

    /**
     * Invoices belonging to this workspace, joined through subscriptions.
     *
     * @return \Illuminate\Database\Eloquent\Builder<Invoice>
     */
    private function workspaceInvoices(Workspace $workspace): \Illuminate\Database\Eloquent\Builder
    {
        return Invoice::query()
            ->join('subscriptions', 'subscriptions.id', '=', 'invoices.subscription_id')
            ->where('subscriptions.workspace_id', $workspace->id);
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyStats(): array
    {
        $now = Carbon::now();
        $chart = [];

        for ($i = self::REVENUE_MONTHS - 1; $i >= 0; $i--) {
            $chart[] = [
                'month' => $now->copy()->startOfMonth()->subMonthsNoOverflow($i)->format('Y-m'),
                'amount' => 0.0,
            ];
        }

        return [
            'occupancy_pct' => 0.0,
            'active_members' => 0,
            'available_seats' => 0,
            'pending_bookings' => 0,
            'overdue_invoices' => 0,
            'revenue_this_month' => 0.0,
            'revenue_last_month' => 0.0,
            'revenue_chart' => $chart,
            'members_by_gender' => $this->genderBuckets(new Collection()),
            'members_by_status' => array_map(
                static fn (SubscriptionStatus $status): array => [
                    'label' => $status->value,
                    'value' => 0,
                ],
                SubscriptionStatus::cases(),
            ),
        ];
    }
}
