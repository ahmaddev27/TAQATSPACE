<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Enums\PricingMethod;
use App\Models\Plan;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Audit\AuditLogger;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Create, edit, archive and delete workspace plans. `SeatTypePricingService`
 * keeps owning `seat_type_prices`; this service only handles `plans`.
 * Archiving hides a plan; subscriptions that already use it are untouched.
 */
class PlanService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Workspace $workspace, array $data, User $actor): Plan
    {
        $attributes = $this->normalize($data);

        $plan = Plan::query()->create([
            'workspace_id' => $workspace->id,
            'code' => $data['code'],
            ...$attributes,
        ]);

        $this->audit->log('finance.plan.created', $plan, actor: $actor, changes: [
            'code' => $plan->code,
            'pricing_method' => $plan->pricing_method->value,
            'price' => $attributes['price'],
        ]);

        return $plan;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Plan $plan, array $data, User $actor): Plan
    {
        if ($plan->isArchived()) {
            throw ValidationException::withMessages(['plan' => __('messages.plan_archived_read_only')]);
        }

        $plan->fill($this->normalize($data));
        $changes = $plan->getDirty();
        $plan->save();

        if ($changes !== []) {
            $this->audit->log('finance.plan.updated', $plan, actor: $actor, changes: $changes);
        }

        return $plan->refresh();
    }

    public function archive(Plan $plan, User $actor): Plan
    {
        if ($plan->isArchived()) {
            return $plan;
        }

        $plan->forceFill(['archived_at' => now()])->save();
        $this->audit->log('finance.plan.archived', $plan, actor: $actor);

        return $plan->refresh();
    }

    /**
     * Removes a plan nothing references; a plan in use is archived instead.
     *
     * @return array{plan: Plan, deleted: bool}
     */
    public function remove(Plan $plan, User $actor): array
    {
        if ($plan->isInUse()) {
            return ['plan' => $this->archive($plan, $actor), 'deleted' => false];
        }

        $plan->delete();
        $this->audit->log('finance.plan.deleted', $plan, actor: $actor, changes: ['code' => $plan->code]);

        return ['plan' => $plan, 'deleted' => true];
    }

    /** The price charged on $on: the promo price while the promotion is running, else the regular price. */
    public function effectivePrice(Plan $plan, CarbonInterface $on): string
    {
        $promoRunning = $plan->promo_price !== null
            && $plan->promo_until !== null
            && $on->lessThanOrEqualTo($plan->promo_until);

        return (string) ($promoRunning ? $plan->promo_price : $plan->price);
    }

    /**
     * Keeps only the fields the pricing method uses and enforces BR-FIN-04.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalize(array $data): array
    {
        $method = PricingMethod::from($data['pricing_method']);
        $dedicated = (bool) ($data['is_dedicated_seat'] ?? false);

        if ($dedicated && ! $method->allowsDedicatedSeat()) {
            throw ValidationException::withMessages([
                'is_dedicated_seat' => __('messages.plan_dedicated_seat_not_allowed'),
            ]);
        }

        $hasAllowance = $method->isMetered() || $method->isBundle();

        return [
            'name_ar' => $data['name_ar'],
            'name_en' => $data['name_en'],
            'pricing_method' => $method->value,
            'price' => $data['price'],
            'overage_price' => $hasAllowance ? ($data['overage_price'] ?? null) : null,
            'included_quantity' => $hasAllowance ? ($data['included_quantity'] ?? null) : null,
            'validity_days' => $method->isBundle() ? ($data['validity_days'] ?? null) : null,
            'is_dedicated_seat' => $dedicated,
            'promo_price' => $data['promo_price'] ?? null,
            'promo_until' => isset($data['promo_until']) ? $this->endOfPromoDay($data['promo_until']) : null,
        ];
    }

    /** A date without a time means "through that day". */
    private function endOfPromoDay(string $value): Carbon
    {
        $moment = Carbon::parse($value);

        return strlen(trim($value)) <= 10 ? $moment->endOfDay() : $moment;
    }
}
