<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\CatalogCategory;
use App\Models\CatalogItem;
use App\Models\Workspace;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Catalog lookups and searches. Every query names the workspace explicitly (on
 * top of the model's tenancy scope) so a record of another workspace can never
 * be returned.
 */
class CatalogRepository
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, CatalogCategory>
     */
    public function paginateCategories(Workspace $workspace, array $filters): LengthAwarePaginator
    {
        $query = CatalogCategory::query()->where('workspace_id', $workspace->id);

        $this->applyArchived($query, $filters);
        $this->applySearch($query, $filters['search'] ?? null, ['code', 'name_ar', 'name_en']);

        if (! empty($filters['kind'])) {
            $query->where('kind', $filters['kind']);
        }

        return $query->orderBy('sort_order')->orderBy('name_en')
            ->paginate($this->perPage($filters))->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, CatalogItem>
     */
    public function paginateItems(Workspace $workspace, array $filters): LengthAwarePaginator
    {
        $query = CatalogItem::query()->with('category')->where('workspace_id', $workspace->id);

        $this->applyArchived($query, $filters);
        $this->applySearch($query, $filters['search'] ?? null, ['code', 'name_ar', 'name_en']);

        if (! empty($filters['catalog_category_id'])) {
            $query->where('catalog_category_id', $filters['catalog_category_id']);
        }

        if (! empty($filters['kind'])) {
            $query->whereHas('category', fn (Builder $category) => $category->where('kind', $filters['kind']));
        }

        return $query->orderBy('code')->paginate($this->perPage($filters))->withQueryString();
    }

    public function findCategory(Workspace $workspace, string $id): CatalogCategory
    {
        return CatalogCategory::query()->where('workspace_id', $workspace->id)->find($id)
            ?? throw (new ModelNotFoundException)->setModel(CatalogCategory::class, [$id]);
    }

    public function findItem(Workspace $workspace, string $id): CatalogItem
    {
        return CatalogItem::query()->with('category')->where('workspace_id', $workspace->id)->find($id)
            ?? throw (new ModelNotFoundException)->setModel(CatalogItem::class, [$id]);
    }

    /**
     * @param  array<int, string>  $ids
     * @return array<int, string> The ids that exist in the workspace.
     */
    public function existingCategoryIds(Workspace $workspace, array $ids): array
    {
        return CatalogCategory::query()
            ->where('workspace_id', $workspace->id)
            ->whereIn('id', $ids)
            ->pluck('id')
            ->all();
    }

    /**
     * @param  Builder<*>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyArchived(Builder $query, array $filters): void
    {
        match ($filters['archived'] ?? 'exclude') {
            'only' => $query->whereNotNull('archived_at'),
            'include' => null,
            default => $query->whereNull('archived_at'),
        };
    }

    /**
     * @param  Builder<*>  $query
     * @param  array<int, string>  $columns
     */
    private function applySearch(Builder $query, mixed $term, array $columns): void
    {
        $term = trim((string) $term);

        if ($term === '') {
            return;
        }

        $like = '%'.addcslashes($term, '%_\\').'%';
        $query->where(function (Builder $inner) use ($columns, $like): void {
            foreach ($columns as $column) {
                $inner->orWhere($column, 'like', $like);
            }
        });
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function perPage(array $filters): int
    {
        return max(1, min((int) ($filters['per_page'] ?? 50), 200));
    }
}
