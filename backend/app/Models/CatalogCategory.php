<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CatalogKind;
use App\Models\Concerns\BelongsToWorkspace;
use Database\Factories\CatalogCategoryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A group of catalog items (or expense lines) a workspace sells or spends on.
 * The `code` is fixed at creation; archiving hides the category without
 * touching anything that already references it.
 *
 * @property string $workspace_id
 * @property CatalogKind $kind
 * @property string $code
 */
class CatalogCategory extends Model
{
    /** @use HasFactory<CatalogCategoryFactory> */
    use BelongsToWorkspace, HasFactory, HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'workspace_id',
        'kind',
        'code',
        'name_ar',
        'name_en',
        'sort_order',
        'archived_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => CatalogKind::class,
            'sort_order' => 'integer',
            'archived_at' => 'datetime',
        ];
    }

    /** @return HasMany<CatalogItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(CatalogItem::class);
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
