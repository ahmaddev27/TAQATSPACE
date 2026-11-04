<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BillingMode;
use App\Enums\PlanType;
use App\Enums\SubscriptionStatus;
use Database\Factories\SubscriptionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Subscription extends Model
{
    /** @use HasFactory<SubscriptionFactory> */
    use HasFactory, HasUuids;

    /**
     * Mirrors the column defaults so a model that was just created (and not
     * refreshed) already behaves like a legacy postpaid subscription.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'billing_mode' => 'postpaid',
        'grace_days' => 0,
        'auto_renew' => false,
    ];

    /** @var list<string> */
    protected $fillable = [
        'member_id',
        'workspace_id',
        'seat_id',
        'plan_type',
        'start_date',
        'end_date',
        'monthly_price',
        'status',
        'cancelled_at',
        'internet_username',
        'internet_password_enc',
        'internet_provisioned_at',
        'plan_id',
        'billing_mode',
        'custom_price',
        'contracted_hours',
        'grace_days',
        'auto_renew',
        'renewed_from_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'plan_type' => PlanType::class,
            'status' => SubscriptionStatus::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'cancelled_at' => 'datetime',
            'monthly_price' => 'decimal:2',
            'internet_password_enc' => 'encrypted',
            'internet_provisioned_at' => 'datetime',
            'billing_mode' => BillingMode::class,
            'custom_price' => 'decimal:2',
            'contracted_hours' => 'decimal:2',
            'grace_days' => 'integer',
            'auto_renew' => 'boolean',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(User::class, 'member_id');
    }

    /** @return BelongsTo<Workspace, $this> */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /** @return BelongsTo<Seat, $this> */
    public function seat(): BelongsTo
    {
        return $this->belongsTo(Seat::class);
    }

    /**
     * Unscoped: invoicing and cron jobs read it with no current workspace.
     *
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class)->withoutGlobalScopes();
    }

    /** @return BelongsTo<Subscription, $this> */
    public function renewedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'renewed_from_id');
    }

    /** Prepaid subscriptions are billed before their period starts (consumed by 5.6). */
    public function isPrepaid(): bool
    {
        return $this->billing_mode === BillingMode::Prepaid;
    }

    /** @param Builder<static> $query */
    public function scopePrepaid(Builder $query): void
    {
        $query->where($this->qualifyColumn('billing_mode'), BillingMode::Prepaid->value);
    }

    /** @return HasMany<Invoice, $this> */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /** @return HasOne<HotspotAccount, $this> */
    public function hotspotAccount(): HasOne
    {
        return $this->hasOne(HotspotAccount::class)->withoutGlobalScopes();
    }

    /**
     * The member's internet login: the hotspot account, falling back to the
     * legacy columns for rows the data migration has not reached yet.
     *
     * @return array{username: string, password: string}|null
     */
    public function internetCredentials(): ?array
    {
        $this->loadMissing('hotspotAccount');
        $account = $this->hotspotAccount;

        if ($account !== null) {
            return ['username' => $account->username, 'password' => $account->password_enc];
        }

        return $this->internet_username === null
            ? null
            : ['username' => $this->internet_username, 'password' => (string) $this->internet_password_enc];
    }
}
