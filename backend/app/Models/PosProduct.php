<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PosProductFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A café/POS catalogue item for a workspace. When `track_stock` is true the
 * `stock_qty` is authoritative and every change is journalled in
 * {@see PosStockMovement}; when false the product is always sellable (e.g. a
 * service like printing).
 */
class PosProduct extends Model
{
    /** @use HasFactory<PosProductFactory> */
    use HasFactory, HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'workspace_id',
        'name',
        'category',
        'sku',
        'price',
        'track_stock',
        'stock_qty',
        'is_active',
        'image_path',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'track_stock' => 'boolean',
            'stock_qty' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Workspace, $this> */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /** @return HasMany<PosStockMovement, $this> */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(PosStockMovement::class);
    }

    /** Whether the product can be sold now (active, and in stock if tracked). */
    public function isSellable(int $qty = 1): bool
    {
        if (! $this->is_active) {
            return false;
        }

        return ! $this->track_stock || $this->stock_qty >= $qty;
    }
}
