<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PlanType;
use App\Enums\SubscriptionStatus;
use Database\Factories\SubscriptionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    /** @use HasFactory<SubscriptionFactory> */
    use HasFactory, HasUuids;

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

    /** @return HasMany<Invoice, $this> */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }
}
