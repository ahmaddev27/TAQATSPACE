<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\CatalogCategory;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Create, edit, archive and reorder a workspace's catalog categories. The
 * `code` and `kind` are written once, at creation.
 */
class CatalogCategoryService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Workspace $workspace, array $data, User $actor): CatalogCategory
    {
        $category = CatalogCategory::query()->create([
            'workspace_id' => $workspace->id,
            'kind' => $data['kind'],
            'code' => $data['code'],
            'name_ar' => $data['name_ar'],
            'name_en' => $data['name_en'],
            'sort_order' => $data['sort_order'] ?? $this->nextSortOrder($workspace),
        ]);

        $this->audit->log('finance.catalog.category.created', $category, actor: $actor, changes: [
            'code' => $category->code,
            'kind' => $data['kind'],
        ]);

        return $category;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(CatalogCategory $category, array $data, User $actor): CatalogCategory
    {
        $category->fill(array_intersect_key($data, array_flip(['name_ar', 'name_en', 'sort_order'])));
        $changes = $category->getDirty();
        $category->save();

        if ($changes !== []) {
            $this->audit->log('finance.catalog.category.updated', $category, actor: $actor, changes: $changes);
        }

        return $category->refresh();
    }

    public function archive(CatalogCategory $category, User $actor): CatalogCategory
    {
        if ($category->isArchived()) {
            return $category;
        }

        $category->forceFill(['archived_at' => now()])->save();
        $this->audit->log('finance.catalog.category.archived', $category, actor: $actor);

        return $category->refresh();
    }

    /**
     * Give the listed categories sort orders 0..n-1 in the given order, in one
     * statement. Every id must belong to the workspace.
     *
     * @param  array<int, string>  $orderedIds
     */
    public function reorder(Workspace $workspace, array $orderedIds, User $actor): void
    {
        $known = CatalogCategory::query()->where('workspace_id', $workspace->id)->whereIn('id', $orderedIds)->count();

        if ($known !== count($orderedIds)) {
            throw new RuntimeException(__('messages.catalog_category_not_found'));
        }

        $cases = implode(' ', array_fill(0, count($orderedIds), 'WHEN ? THEN ?'));
        $bindings = [];

        foreach (array_values($orderedIds) as $position => $id) {
            array_push($bindings, $id, $position);
        }

        DB::update(
            "UPDATE catalog_categories SET sort_order = CASE id {$cases} END WHERE workspace_id = ? AND id IN (".implode(',', array_fill(0, count($orderedIds), '?')).')',
            [...$bindings, $workspace->id, ...$orderedIds],
        );

        $this->audit->log('finance.catalog.category.reordered', $workspace, actor: $actor, changes: ['ids' => $orderedIds]);
    }

    private function nextSortOrder(Workspace $workspace): int
    {
        $max = CatalogCategory::query()->where('workspace_id', $workspace->id)->max('sort_order');

        return $max === null ? 0 : ((int) $max) + 1;
    }
}
