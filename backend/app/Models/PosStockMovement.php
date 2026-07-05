<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StockMovementType;
use Database\Factories\PosStockMovementFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One journalled change to a product's stock (restock, adjustment, or sale).
 * Append-only — the running `stock_after` is snapshotted per row for audit.
 */
class PosStockMovement extends Model
{
    /** @use HasFactory<PosStockMovementFactory> */
    use HasFactory, HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'pos_product_id',
        'type',
        'qty_change',
        'stock_after',
        'note',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => StockMovementType::class,
            'qty_change' => 'integer',
            'stock_after' => 'integer',
        ];
    }

    /** @return BelongsTo<PosProduct, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(PosProduct::class, 'pos_product_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
