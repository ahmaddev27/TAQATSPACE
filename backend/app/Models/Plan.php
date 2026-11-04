<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PricingMethod;
use App\Models\Concerns\BelongsToWorkspace;
use Database\Factories\PlanFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A workspace's financial plan (monthly, weekly, daily, hourly, per GB or
 * bundle). The `code` is fixed at creation; archiving hides a plan without
 * touching subscriptions that already use it.
 *
 * @property string $workspace_id
 * @property string $code
 * @property PricingMethod $pricing_method
 * @property bool $is_dedicated_seat
 */
class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use BelongsToWorkspace, HasFactory, HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'workspace_id',
        'code',
        'name_ar',
        'name_en',
        'pricing_method',
        'price',
        'overage_price',
        'included_quantity',
        'validity_days',
        'is_dedicated_seat',
        'promo_price',
        'promo_until',
        'archived_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pricing_method' => PricingMethod::class,
            'price' => 'decimal:2',
            'overage_price' => 'decimal:2',
            'included_quantity' => 'decimal:2',
            'validity_days' => 'integer',
            'is_dedicated_seat' => 'boolean',
            'promo_price' => 'decimal:2',
            'promo_until' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    /** @param Builder<static> $query */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull($this->qualifyColumn('archived_at'));
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    /** @return HasMany<Subscription, $this> */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Whether any record references this plan; a plan in use is archived
     * instead of deleted. Extend this when another table starts pointing here.
     */
    public function isInUse(): bool
    {
        return $this->subscriptions()->exists();
    }
}
