<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Enums\ChequeStatus;
use App\Enums\PaymentMethod;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Receipt;
use App\Models\ReceiptAllocation;
use App\Models\Workspace;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Receipt lookups. Every list and find names the workspace, so another
 * workspace's receipt is never returned; the per-invoice helpers are scoped
 * by an invoice the caller has already resolved in its workspace.
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

    /**
     * Ids of the live receipts whose money counts as paid on the invoice (a
     * cheque only once cleared).
     *
     * @return array<int, string>
     */
    public function collectedIdsFor(Invoice $invoice): array
    {
        return $this->collected()->whereHas('allocations', static fn (Builder $query) => $query->where('invoice_id', $invoice->id))
            ->orderBy('id')->pluck('id')->all();
    }

    /**
     * Lock receipts for update, in id order so two callers never deadlock.
     *
     * @param  array<int, string>  $ids
     * @return Collection<int, Receipt>
     */
    public function lockByIds(array $ids): Collection
    {
        return $ids === [] ? new Collection : Receipt::query()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
    }

    /** @param  array<int, string>  $receiptIds */
    public function anyAllocatedElsewhere(array $receiptIds, Invoice $invoice): bool
    {
        return $receiptIds !== [] && ReceiptAllocation::query()
            ->whereIn('receipt_id', $receiptIds)
            ->where('invoice_id', '!=', $invoice->id)
            ->exists();
    }

    /** What live, collected receipts have paid on the invoice: the part of `amount_paid` a receipt backs. */
    public function collectedTotalFor(Invoice $invoice): string
    {
        $sum = ReceiptAllocation::query()
            ->where('invoice_id', $invoice->id)
            ->whereIn('receipt_id', $this->collected()->select('id'))
            ->sum('amount');

        return bcadd((string) $sum, '0', 2);
    }

    /** Whether a legacy payment row on the invoice has not been copied into a receipt yet. */
    public function hasPaymentsWithoutReceipt(Invoice $invoice): bool
    {
        return InvoicePayment::query()
            ->where('invoice_id', $invoice->id)
            ->whereNotIn('id', Receipt::query()->whereNotNull('legacy_payment_id')->select('legacy_payment_id'))
            ->exists();
    }

    /** @return Builder<Receipt> live receipts whose money counts as collected */
    private function collected(): Builder
    {
        return Receipt::query()
            ->whereNull('voided_at')
            ->where(static function (Builder $query): void {
                $query->where('method', '!=', PaymentMethod::Cheque->value)
                    ->orWhere('cheque_status', ChequeStatus::Cleared->value);
            });
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
