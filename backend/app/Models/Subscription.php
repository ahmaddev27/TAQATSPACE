<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PlanType;
use App\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    /** @use HasFactory<\Database\Factories\SubscriptionFactory> */
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
