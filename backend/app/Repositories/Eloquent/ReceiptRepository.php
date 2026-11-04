<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Receipt;
use App\Models\Workspace;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Receipt lookups. Every query names the workspace, so another workspace's
 * receipt is never returned.
 */
class ReceiptRepository
{
    private const WITH = ['allocations.invoice:id,invoice_number,amount', 'customer:id,name'];

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Receipt>
     */
    public function paginate(Workspace $workspace, array $filters): LengthAwarePaginator
    {
        $query = Receipt::query()->with(self::WITH)->where('workspace_id', $workspace->id);

        foreach (['customer_id', 'method', 'cheque_status'] as $column) {
            if (! empty($filters[$column])) {
                $query->where($column, $filters[$column]);
            }
        }

        $this->applyState($query, $filters['state'] ?? null);
        $this->applyDates($query, $filters);

        $sort = $filters['sort'] ?? 'received_at';
        $direction = ($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $perPage = max(1, min((int) ($filters['per_page'] ?? 50), 200));

        return $query->orderBy($sort, $direction)->orderBy('id')->paginate($perPage)->withQueryString();
    }

    public function find(Workspace $workspace, string $id): Receipt
    {
        return Receipt::query()->with(self::WITH)->where('workspace_id', $workspace->id)->find($id)
            ?? throw (new ModelNotFoundException)->setModel(Receipt::class, [$id]);
    }

    /** @param  Builder<Receipt>  $query */
    private function applyState(Builder $query, mixed $state): void
    {
        match ($state) {
            'voided' => $query->whereNotNull('voided_at'),
            'active' => $query->whereNull('voided_at'),
            default => null,
        };
    }

    /**
     * Date range over whichever date the list is sorted by.
     *
     * @param  Builder<Receipt>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyDates(Builder $query, array $filters): void
    {
        $column = $filters['sort'] ?? 'received_at';

        if (! empty($filters['from'])) {
            $query->where($column, '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->where($column, '<=', $filters['to'].' 23:59:59');
        }
    }
}
