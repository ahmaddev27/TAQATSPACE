<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CatalogUnit;
use App\Models\Concerns\BelongsToWorkspace;
use Database\Factories\CatalogItemFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A priced thing a workspace sells (or buys): the single source later used by
 * invoices, POS and expenses. The `code` is fixed at creation.
 *
 * @property string $workspace_id
 * @property string $catalog_category_id
 * @property string $code
 * @property CatalogUnit $unit
 */
class CatalogItem extends Model
{
    /** @use HasFactory<CatalogItemFactory> */
    use BelongsToWorkspace, HasFactory, HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'workspace_id',
        'catalog_category_id',
        'code',
        'name_ar',
        'name_en',
        'unit',
        'price',
        'archived_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit' => CatalogUnit::class,
            'price' => 'decimal:2',
            'archived_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<CatalogCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(CatalogCategory::class, 'catalog_category_id');
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
}
