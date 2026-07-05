<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PosOrderItemFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of a POS order, with the product name + unit price snapshotted at
 * sale time so history is immune to later catalogue changes.
 */
class PosOrderItem extends Model
{
    /** @use HasFactory<PosOrderItemFactory> */
    use HasFactory, HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'pos_order_id',
        'pos_product_id',
        'name',
        'unit_price',
        'qty',
        'line_total',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'qty' => 'integer',
            'line_total' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<PosOrder, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(PosOrder::class, 'pos_order_id');
    }

    /** @return BelongsTo<PosProduct, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(PosProduct::class, 'pos_product_id');
    }
}
