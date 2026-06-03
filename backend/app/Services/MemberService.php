<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\SeatStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Invoice;
use App\Models\Seat;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class MemberService
{
    /** @var array<string, string> */
    private const SORTABLE = [
        'join_date' => 'subscriptions.start_date',
        'name' => 'users.name',
        'status' => 'subscriptions.status',
    ];

    /**
     * Paginated roster of this workspace's members (one row per subscription).
     *
     * @param  array{status?: string|null, search?: string|null, sort?: string|null, direction?: string|null, per_page?: int|null}  $filters
     * @return LengthAwarePaginator<int, Subscription>
     */
    public function paginateMembers(Workspace $workspace, array $filters): LengthAwarePaginator
    {
        $query = Subscription::query()
            ->with(['member', 'seat'])
            ->join('users', 'users.id', '=', 'subscriptions.member_id')
            ->where('subscriptions.workspace_id', $workspace->id)
            ->select('subscriptions.*');

        $this->applyStatusFilter($query, $filters['status'] ?? null);
        $this->applySearch($query, $filters['search'] ?? null);
        $this->applySort($query, $filters['sort'] ?? null, $filters['direction'] ?? null);

        $perPage = (int) ($filters['per_page'] ?? 15);

        return $query->paginate($perPage);
    }

    /**
     * Full member detail for a member of THIS workspace only.
     *
     * @return array<string, mixed>|null  null when the user is not a member of this workspace
     */
    public function memberDetail(Workspace $workspace, User $member): ?array
    {
        $subscription = $this->resolveSubscription($workspace, $member);

        if ($subscription === null) {
            return null;
        }

        $invoices = Invoice::query()
            ->where('subscription_id', $subscription->id)
            ->orderByDesc('due_date')
            ->get();

        return [
            'user' => [
                'id' => $member->id,
                'name' => $member->name,
                'email' => $member->email,
                'phone' => $member->phone,
                'specialty' => $member->specialty,
                'bio' => $member->bio,
                'avatar' => $member->avatar,
            ],
            'subscription' => [
                'id' => $subscription->id,
                'status' => $subscription->status->value,
                'plan_type' => $subscription->plan_type->value,
                'monthly_price' => $subscription->monthly_price,
                'start_date' => $subscription->start_date?->toDateString(),
                'end_date' => $subscription->end_date?->toDateString(),
                'cancelled_at' => $subscription->cancelled_at?->toIso8601String(),
            ],
            'seat' => $subscription->seat === null ? null : [
                'id' => $subscription->seat->id,
                'seat_number' => $subscription->seat->seat_number,
                'type' => $subscription->seat->type->value,
                'status' => $subscription->seat->status->value,
            ],
            'invoices' => $invoices->map($this->formatInvoice(...))->all(),
            'payment_history' => $invoices
                ->where('status', InvoiceStatus::Paid)
                ->map($this->formatInvoice(...))
                ->values()
                ->all(),
        ];
    }

    /**
     * Activate or suspend a member of THIS workspace.
     *
     * Suspending cancels the subscription and frees the assigned seat
     * atomically. Scoped to the workspace so foreign members are untouched.
     *
     * @return Subscription|null  null when the user is not a member of this workspace
     */
    public function updateStatus(Workspace $workspace, User $member, string $status): ?Subscription
    {
        $subscription = $this->resolveSubscription($workspace, $member);

        if ($subscription === null) {
            return null;
        }

        $target = SubscriptionStatus::from($status === 'suspended' ? 'cancelled' : 'active');

        DB::transaction(function () use ($workspace, $subscription, $target): void {
            if ($target === SubscriptionStatus::Cancelled) {
                $subscription->update([
                    'status' => SubscriptionStatus::Cancelled->value,
                    'cancelled_at' => Carbon::now(),
                ]);

                $this->freeSeat($workspace, $subscription);
            } else {
                $subscription->update([
                    'status' => SubscriptionStatus::Active->value,
                    'cancelled_at' => null,
                ]);
            }
        });

        Cache::forget("workspace:{$workspace->id}:dashboard_stats");

        return $subscription->refresh()->load(['member', 'seat']);
    }

    private function freeSeat(Workspace $workspace, Subscription $subscription): void
    {
        if ($subscription->seat_id !== null) {
            Seat::query()
                ->where('id', $subscription->seat_id)
                ->where('workspace_id', $workspace->id)
                ->update([
                    'status' => SeatStatus::Available->value,
                    'assigned_member_id' => null,
                ]);
        }

        Seat::query()
            ->where('workspace_id', $workspace->id)
            ->where('assigned_member_id', $subscription->member_id)
            ->update([
                'status' => SeatStatus::Available->value,
                'assigned_member_id' => null,
            ]);

        $subscription->update(['seat_id' => null]);
    }

    /**
     * @param  Builder<Subscription>  $query
     */
    private function applyStatusFilter(Builder $query, ?string $status): void
    {
        if ($status === null || $status === 'all') {
            return;
        }

        if ($status === 'suspended') {
            $query->whereIn('subscriptions.status', [
                SubscriptionStatus::Suspended->value,
                SubscriptionStatus::Cancelled->value,
            ]);

            return;
        }

        $query->where('subscriptions.status', $status);
    }

    /**
     * @param  Builder<Subscription>  $query
     */
    private function applySearch(Builder $query, ?string $search): void
    {
        $term = $search === null ? '' : trim($search);

        if ($term === '') {
            return;
        }

        $like = '%'.$term.'%';

        $query->where(function (Builder $q) use ($like): void {
            $q->where('users.name', 'like', $like)
                ->orWhere('users.email', 'like', $like);
        });
    }

    /**
     * @param  Builder<Subscription>  $query
     */
    private function applySort(Builder $query, ?string $sort, ?string $direction): void
    {
        $column = self::SORTABLE[$sort] ?? self::SORTABLE['join_date'];
        $dir = strtolower((string) $direction) === 'asc' ? 'asc' : 'desc';

        $query->orderBy($column, $dir);
    }

    private function resolveSubscription(Workspace $workspace, User $member): ?Subscription
    {
        return Subscription::query()
            ->with('seat')
            ->where('workspace_id', $workspace->id)
            ->where('member_id', $member->id)
            ->latest('start_date')
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function formatInvoice(Invoice $invoice): array
    {
        return [
            'id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'amount' => $invoice->amount,
            'status' => $invoice->status->value,
            'due_date' => $invoice->due_date?->toDateString(),
            'paid_at' => $invoice->paid_at?->toIso8601String(),
        ];
    }
}
