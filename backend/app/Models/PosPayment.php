<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentMethod;
use Database\Factories\PosPaymentFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A payment recorded against a POS order (cash or transfer — manual tracking).
 */
class PosPayment extends Model
{
    /** @use HasFactory<PosPaymentFactory> */
    use HasFactory, HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'pos_order_id',
        'amount',
        'method',
        'received_by',
        'paid_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'method' => PaymentMethod::class,
            'paid_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<PosOrder, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(PosOrder::class, 'pos_order_id');
    }
}
