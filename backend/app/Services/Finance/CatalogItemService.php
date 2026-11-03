<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\CatalogItem;
use App\Models\PosProduct;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Create, edit and archive catalog items. The `code` is written once, at
 * creation. Archiving only hides an item: invoices and orders that already use
 * it keep their own copy of the name and price.
 */
class CatalogItemService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Workspace $workspace, array $data, User $actor): CatalogItem
    {
        $item = CatalogItem::query()->create([
            'workspace_id' => $workspace->id,
            'catalog_category_id' => $data['catalog_category_id'],
            'code' => $data['code'],
            'name_ar' => $data['name_ar'],
            'name_en' => $data['name_en'],
            'unit' => $data['unit'],
            'price' => $data['price'],
        ]);

        $this->audit->log('finance.catalog.item.created', $item, actor: $actor, changes: [
            'code' => $item->code,
            'price' => $data['price'],
        ]);

        return $item->load('category');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(CatalogItem $item, array $data, User $actor): CatalogItem
    {
        $allowed = ['catalog_category_id', 'name_ar', 'name_en', 'unit', 'price'];

        DB::transaction(function () use ($item, $data, $actor, $allowed): void {
            $item->fill(array_intersect_key($data, array_flip($allowed)));
            $changes = $item->getDirty();
            $item->save();

            if ($changes === []) {
                return;
            }

            $this->mirrorToPosProducts($item, $changes);
            $this->audit->log('finance.catalog.item.updated', $item, actor: $actor, changes: $changes);
        });

        return $item->refresh()->load('category');
    }

    public function archive(CatalogItem $item, User $actor): CatalogItem
    {
        if ($item->isArchived()) {
            return $item;
        }

        $item->forceFill(['archived_at' => now()])->save();
        $this->audit->log('finance.catalog.item.archived', $item, actor: $actor);

        return $item->refresh()->load('category');
    }

    /**
     * POS keeps its own name/price columns (orders snapshot them), so a change
     * to the linked item is copied across in one statement.
     *
     * @param  array<string, mixed>  $changes
     */
    private function mirrorToPosProducts(CatalogItem $item, array $changes): void
    {
        $mirrored = array_filter([
            'name' => $changes['name_ar'] ?? null,
            'price' => $changes['price'] ?? null,
        ], static fn (mixed $value): bool => $value !== null);

        if ($mirrored === []) {
            return;
        }

        PosProduct::query()
            ->where('workspace_id', $item->workspace_id)
            ->where('catalog_item_id', $item->id)
            ->update($mirrored);
    }
}
