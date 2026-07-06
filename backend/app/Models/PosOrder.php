<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PosOrderSource;
use App\Enums\PosOrderStatus;
use Database\Factories\PosOrderFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A café/POS sale for a workspace: a set of {@see PosOrderItem} lines settled by
 * one or more {@see PosPayment} rows. Standalone from the subscription-billing
 * `invoices` chain — it serves walk-ins and members without a subscription.
 */
class PosOrder extends Model
{
    /** @use HasFactory<PosOrderFactory> */
    use HasFactory, HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'workspace_id',
        'order_number',
        'source',
        'status',
        'member_id',
        'customer_name',
        'cashier_id',
        'subtotal',
        'discount',
        'total',
        'paid_at',
        'refunded_at',
        'refunded_by',
        'note',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => PosOrderSource::class,
            'status' => PosOrderStatus::class,
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'total' => 'decimal:2',
            'paid_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }

    /** Whether this order has been settled (payment is decoupled from status). */
    public function isPaid(): bool
    {
        return $this->paid_at !== null;
    }

    /** @return BelongsTo<Workspace, $this> */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /** @return HasMany<PosOrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(PosOrderItem::class);
    }

    /** @return HasMany<PosPayment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(PosPayment::class);
    }

    /** @return BelongsTo<User, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(User::class, 'member_id');
    }

    /** @return BelongsTo<User, $this> */
    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    /** @return BelongsTo<User, $this> */
    public function refundedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'refunded_by');
    }
}
