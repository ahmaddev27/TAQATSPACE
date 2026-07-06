<?php

declare(strict_types=1);

namespace App\Services\Pos;

use App\Enums\StockMovementType;
use App\Models\PosProduct;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Catalogue + inventory management for a workspace's POS. All reads and writes
 * are scoped to the given workspace; a product from another workspace is never
 * returned or mutated. Stock only ever changes through {@see adjustStock} (or a
 * sale in the order flow), each change journalled to `pos_stock_movements`.
 */
class PosProductService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, PosProduct>
     */
    public function paginate(Workspace $workspace, array $filters): LengthAwarePaginator
    {
        return $this->query($workspace, $filters)
            ->orderBy('name')
            ->paginate($this->perPage($filters))
            ->withQueryString();
    }

    /**
     * Create a catalogue item. When it tracks stock and opens with a positive
     * quantity, that opening balance is journalled as a restock.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(Workspace $workspace, array $data, User $actor): PosProduct
    {
        return DB::transaction(function () use ($workspace, $data, $actor): PosProduct {
            $trackStock = (bool) ($data['track_stock'] ?? true);
            $opening = $trackStock ? (int) ($data['stock_qty'] ?? 0) : 0;

            $product = PosProduct::query()->create([
                'workspace_id' => $workspace->id,
                'name' => $data['name'],
                'category' => $data['category'] ?? null,
                'sku' => $data['sku'] ?? null,
                'price' => $data['price'],
                'track_stock' => $trackStock,
                'stock_qty' => $opening,
                'is_active' => $data['is_active'] ?? true,
            ]);

            if ($opening > 0) {
                $this->journal($product, StockMovementType::Restock, $opening, $actor, __('messages.pos_opening_stock'));
            }

            return $product;
        });
    }

    /**
     * Update catalogue fields (name, price, category, activity). Stock is NOT
     * changed here — it only moves through {@see adjustStock} so every change is
     * auditable.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Workspace $workspace, PosProduct $product, array $data): PosProduct
    {
        $this->ensureBelongs($workspace, $product);

        $product->fill(array_filter(
            [
                'name' => $data['name'] ?? null,
                'category' => $data['category'] ?? null,
                'sku' => $data['sku'] ?? null,
                'price' => $data['price'] ?? null,
                'track_stock' => $data['track_stock'] ?? null,
                'is_active' => $data['is_active'] ?? null,
            ],
            static fn ($value): bool => $value !== null,
        ))->save();

        return $product->refresh();
    }

    public function delete(Workspace $workspace, PosProduct $product): void
    {
        $this->ensureBelongs($workspace, $product);
        $product->delete();
    }

    /**
     * Apply a signed stock change (restock or manual adjustment) and journal it.
     * Rejects a change that would drive stock negative.
     */
    public function adjustStock(
        Workspace $workspace,
        PosProduct $product,
        StockMovementType $type,
        int $qtyChange,
        ?string $note,
        User $actor,
    ): PosProduct {
        $this->ensureBelongs($workspace, $product);

        if (! $product->track_stock) {
            throw new RuntimeException(__('messages.pos_product_not_tracked'));
        }

        if ($qtyChange === 0) {
            throw new RuntimeException(__('messages.pos_stock_no_change'));
        }

        return DB::transaction(function () use ($product, $type, $qtyChange, $note, $actor): PosProduct {
            $locked = PosProduct::query()->lockForUpdate()->findOrFail($product->id);
            $newQty = $locked->stock_qty + $qtyChange;

            if ($newQty < 0) {
                throw new RuntimeException(__('messages.pos_stock_insufficient'));
            }

            $locked->forceFill(['stock_qty' => $newQty])->save();
            $this->journal($locked, $type, $qtyChange, $actor, $note);

            return $locked->refresh();
        });
    }

    /**
     * Correct a tracked product's stock to an ABSOLUTE count (a stock-take fix).
     * The delta is computed under lock and journalled as an adjustment, so the
     * final count is exactly $newQty regardless of concurrent changes.
     */
    public function setStock(
        Workspace $workspace,
        PosProduct $product,
        int $newQty,
        ?string $note,
        User $actor,
    ): PosProduct {
        $this->ensureBelongs($workspace, $product);

        if (! $product->track_stock) {
            throw new RuntimeException(__('messages.pos_product_not_tracked'));
        }

        if ($newQty < 0) {
            throw new RuntimeException(__('messages.pos_stock_insufficient'));
        }

        return DB::transaction(function () use ($product, $newQty, $note, $actor): PosProduct {
            $locked = PosProduct::query()->lockForUpdate()->findOrFail($product->id);
            $delta = $newQty - $locked->stock_qty;

            if ($delta === 0) {
                throw new RuntimeException(__('messages.pos_stock_no_change'));
            }

            $locked->forceFill(['stock_qty' => $newQty])->save();
            $this->journal($locked, StockMovementType::Adjustment, $delta, $actor, $note);

            return $locked->refresh();
        });
    }

    /** Append a stock-movement ledger row (never mutated after creation). */
    private function journal(PosProduct $product, StockMovementType $type, int $qtyChange, User $actor, ?string $note): void
    {
        $product->stockMovements()->create([
            'type' => $type->value,
            'qty_change' => $qtyChange,
            'stock_after' => $product->stock_qty,
            'note' => $note,
            'created_by' => $actor->id,
        ]);
    }

    private function ensureBelongs(Workspace $workspace, PosProduct $product): void
    {
        if ((string) $product->workspace_id !== (string) $workspace->id) {
            abort(404, __('messages.pos_product_not_found'));
        }
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<PosProduct>
     */
    private function query(Workspace $workspace, array $filters): Builder
    {
        $query = PosProduct::query()->where('workspace_id', $workspace->id);

        if (array_key_exists('is_active', $filters) && $filters['is_active'] !== null) {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        if (! empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (! empty($filters['search'])) {
            $like = '%'.trim((string) $filters['search']).'%';
            $query->where(function (Builder $inner) use ($like): void {
                $inner->where('name', 'like', $like)->orWhere('sku', 'like', $like);
            });
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function perPage(array $filters): int
    {
        return max(1, min((int) ($filters['per_page'] ?? 50), 200));
    }
}
