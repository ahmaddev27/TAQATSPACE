<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\InvoiceStatus;
use App\Enums\SeatStatus;
use App\Enums\SubscriptionStatus;
use App\Models\BookingRequest;
use App\Models\Invoice;
use App\Models\Seat;
use App\Models\Subscription;
use App\Models\Workspace;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class OwnerDashboardService
{
    private const CACHE_TTL_SECONDS = 300;

    private const REVENUE_MONTHS = 6;

    /**
     * Build (and cache) the owner's dashboard snapshot.
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

        return Cache::remember(
            "workspace:{$workspace->id}:dashboard_stats",
            self::CACHE_TTL_SECONDS,
            fn (): array => $this->buildStats($workspace),
        );
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
        ];
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
        ];
    }
}
