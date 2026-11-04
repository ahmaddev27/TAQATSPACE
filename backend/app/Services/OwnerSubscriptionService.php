<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PlanType;
use App\Enums\PricingMethod;
use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Audit\AuditLogger;
use App\Services\Finance\SubscriptionPricingService;
use App\Services\Hotspot\HotspotAccountService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Owner-facing subscription lifecycle for a single workspace: a filterable
 * roster the owner uses to track status, renew an expiring/ending term, or
 * cancel and free the seat.
 *
 * Renewal follows the manual-billing model — it extends the period and raises
 * the NEXT pending invoice (reusing {@see InvoiceService}); it never settles
 * payment. Cancellation mirrors the member-side cancel and frees the seat.
 */
class OwnerSubscriptionService
{
    /** @var array<string, string> */
    private const SORTABLE = [
        'start_date' => 'subscriptions.start_date',
        'end_date' => 'subscriptions.end_date',
        'name' => 'users.name',
        'status' => 'subscriptions.status',
        'monthly_price' => 'subscriptions.monthly_price',
    ];

    /** @var list<string> */
    private const FINANCIAL_TERMS = [
        'plan_id', 'billing_mode', 'custom_price', 'contracted_hours', 'grace_days', 'auto_renew',
    ];

    public function __construct(
        private readonly InvoiceService $invoices,
        private readonly SubscriptionPricingService $pricing,
        private readonly AuditLogger $audit,
        private readonly HotspotAccountService $hotspot,
    ) {}

    /**
     * Set the financial terms (plan, billing mode, negotiated price, hours,
     * grace days, auto-renew). The legacy `monthly_price` is never touched, so
     * clearing the plan and custom price falls back to it.
     *
     * @param  array<string, mixed>  $terms  only the keys present are changed
     *
     * @throws ValidationException when the terms do not fit the plan or seat
     * @throws RuntimeException when the subscription belongs to another workspace
     */
    public function updateFinancialTerms(Workspace $workspace, Subscription $subscription, array $terms, User $actor): Subscription
    {
        $this->assertOwnedBy($workspace, $subscription);

        $priceBefore = $this->pricing->effectivePrice($subscription);
        $subscription->fill(Arr::only($terms, self::FINANCIAL_TERMS))->load('plan');
        $this->assertTermsFit($subscription);

        $changes = $subscription->getDirty();
        $subscription->save();

        if ($changes !== []) {
            $this->auditTerms($subscription, $actor, $priceBefore, $changes);
        }

        return $subscription->refresh()->load(['member', 'seat', 'plan']);
    }

    /**
     * Contracted hours only make sense on an hourly plan; a dedicated-seat
     * plan needs a seat to reserve (BR-FIN-04, 5.17).
     *
     * @throws ValidationException
     */
    private function assertTermsFit(Subscription $subscription): void
    {
        $plan = $subscription->plan;

        if ($plan?->isArchived() && $subscription->isDirty('plan_id')) {
            throw ValidationException::withMessages(['plan_id' => __('messages.plan_archived_read_only')]);
        }

        if ($subscription->contracted_hours !== null && $plan?->pricing_method !== PricingMethod::Hourly) {
            throw ValidationException::withMessages(['contracted_hours' => __('messages.subscription_hours_need_hourly_plan')]);
        }

        if ($plan?->is_dedicated_seat && $subscription->seat_id === null) {
            throw ValidationException::withMessages(['plan_id' => __('messages.subscription_plan_needs_seat')]);
        }
    }

    /** @param array<string, mixed> $changes */
    private function auditTerms(Subscription $subscription, User $actor, string $priceBefore, array $changes): void
    {
        $subscription->load('plan');
        $priceAfter = $this->pricing->effectivePrice($subscription);

        if ($priceAfter !== $priceBefore) {
            $this->audit->log('finance.subscription.price_changed', $subscription, actor: $actor, changes: [
                'from' => $priceBefore,
                'to' => $priceAfter,
            ]);
        }

        $this->audit->log('finance.subscription.terms_updated', $subscription, actor: $actor, changes: $changes);
    }

    /**
     * Paginated subscriptions for THIS workspace, with the member + seat loaded.
     *
     * @param  array{status?: string|null, search?: string|null, sort?: string|null, direction?: string|null, per_page?: int|null}  $filters
     * @return LengthAwarePaginator<int, Subscription>
     */
    public function paginateForWorkspace(Workspace $workspace, array $filters): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 200));

        return $this->rosterQuery($workspace, $filters)
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Renew a subscription for one more plan cycle.
     *
     * Pushes `end_date` forward by a cycle (a month for monthly/custom plans, a
     * day for daily), reactivates the subscription, and raises the next pending
     * invoice for the new period — consistent with manual, owner-tracked billing.
     *
     * @throws RuntimeException when the subscription belongs to another workspace
     */
    public function renew(Workspace $workspace, Subscription $subscription): Subscription
    {
        $this->assertOwnedBy($workspace, $subscription);

        if ($subscription->status === SubscriptionStatus::Cancelled) {
            throw new RuntimeException(__('messages.subscription_already_cancelled'));
        }

        // Block early renewal: the running term must have reached its end date
        // before it can be extended for another cycle.
        if (
            $subscription->end_date !== null
            && $subscription->end_date->gt(Carbon::today())
        ) {
            throw new RuntimeException(__('messages.subscription_not_due_for_renewal'));
        }

        return DB::transaction(function () use ($workspace, $subscription): Subscription {
            $nextEnd = $this->nextPeriodEnd($subscription);

            $subscription->update([
                'status' => SubscriptionStatus::Active->value,
                'end_date' => $nextEnd->toDateString(),
                'cancelled_at' => null,
            ]);

            $this->invoices->createForRenewal($subscription, $nextEnd);
            $this->hotspot->resume($subscription);

            Cache::forget("workspace:{$workspace->id}:dashboard_stats");

            return $subscription->refresh()->load(['member', 'seat']);
        });
    }

    /**
     * Cancel a subscription and free its seat.
     *
     * Delegates the cancel + seat-release to {@see BookingService} so the owner
     * and member paths share one implementation (no duplicated lifecycle logic).
     *
     * @throws RuntimeException when the subscription belongs to another workspace
     */
    public function cancel(Workspace $workspace, Subscription $subscription, BookingService $bookings): Subscription
    {
        $this->assertOwnedBy($workspace, $subscription);

        $cancelled = $bookings->cancelSubscription($subscription);

        Cache::forget("workspace:{$workspace->id}:dashboard_stats");

        return $cancelled->load(['member', 'seat']);
    }

    /**
     * Build the workspace subscription roster query with status/search filters
     * and sort applied. One row per subscription, newest term first by default.
     *
     * @param  array{status?: string|null, search?: string|null, sort?: string|null, direction?: string|null}  $filters
     * @return Builder<Subscription>
     */
    private function rosterQuery(Workspace $workspace, array $filters): Builder
    {
        $query = Subscription::query()
            ->with([
                'member:id,name,email,phone,specialty,avatar',
                // The member's internet packages within THIS workspace, so the
                // roster can show the package and fold its price into the total.
                'member.internetPackages' => fn ($q) => $q
                    ->where('internet_packages.workspace_id', $workspace->id),
                'seat:id,seat_number,type,status',
                'plan',
                'hotspotAccount',
            ])
            ->join('users', 'users.id', '=', 'subscriptions.member_id')
            ->where('subscriptions.workspace_id', $workspace->id)
            ->select('subscriptions.*');

        $this->applyStatusFilter($query, $filters['status'] ?? null);
        $this->applySearch($query, $filters['search'] ?? null);
        $this->applySort($query, $filters['sort'] ?? null, $filters['direction'] ?? null);

        return $query;
    }

    /**
     * @param  Builder<Subscription>  $query
     */
    private function applyStatusFilter(Builder $query, ?string $status): void
    {
        if ($status === null || $status === 'all') {
            return;
        }

        if (SubscriptionStatus::tryFrom($status) !== null) {
            $query->where('subscriptions.status', $status);
        }
    }

    /**
     * @param  Builder<Subscription>  $query
     */
    private function applySearch(Builder $query, ?string $search): void
    {
        $term = trim((string) ($search ?? ''));

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
        $column = self::SORTABLE[$sort] ?? self::SORTABLE['start_date'];
        $dir = strtolower((string) $direction) === 'asc' ? 'asc' : 'desc';

        $query->orderBy($column, $dir);
    }

    /**
     * The end date of the next period: extend from the current `end_date` when
     * the term still runs into the future, otherwise from today, so a lapsed
     * subscription resumes from now rather than back-dating the new period.
     */
    private function nextPeriodEnd(Subscription $subscription): Carbon
    {
        $today = Carbon::today();
        $anchor = $subscription->end_date !== null && $subscription->end_date->gt($today)
            ? $subscription->end_date->copy()
            : $today->copy();

        return $subscription->plan_type === PlanType::Daily
            ? $anchor->addDay()
            : $anchor->addMonth();
    }

    private function assertOwnedBy(Workspace $workspace, Subscription $subscription): void
    {
        if ($subscription->workspace_id !== $workspace->id) {
            throw new RuntimeException(__('messages.subscription_no_access'));
        }
    }
}
