<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\Workspace;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Credit-note lookups. Every query names the workspace, so another
 * workspace's credit note is never returned.
 */
class CreditNoteRepository
{
    // The money columns feed InvoiceBalanceService::refundDue; subscription_id lets the PDF find the workspace name.
    private const WITH = ['invoice:id,invoice_number,customer_id,subscription_id,amount,credited_amount,amount_paid', 'invoice.customer:id,name', 'issuer:id,name'];

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, CreditNote>
     */
    public function paginate(Workspace $workspace, array $filters): LengthAwarePaginator
    {
        $query = CreditNote::query()->with(self::WITH)->where('workspace_id', $workspace->id);

        if (! empty($filters['invoice_id'])) {
            $query->where('invoice_id', $filters['invoice_id']);
        }

        if (! empty($filters['from'])) {
            $query->where('issued_at', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->where('issued_at', '<=', $filters['to'].' 23:59:59');
        }

        $perPage = max(1, min((int) ($filters['per_page'] ?? 50), 200));

        return $query->orderByDesc('issued_at')->orderBy('id')->paginate($perPage)->withQueryString();
    }

    /** The invoice a credit note is raised on, only if it belongs to the workspace. */
    public function findInvoice(Workspace $workspace, string $id): Invoice
    {
        return Invoice::query()->forWorkspace($workspace->id)->find($id)
            ?? throw (new ModelNotFoundException)->setModel(Invoice::class, [$id]);
    }

    public function find(Workspace $workspace, string $id): CreditNote
    {
        return CreditNote::query()->with(self::WITH)->where('workspace_id', $workspace->id)->find($id)
            ?? throw (new ModelNotFoundException)->setModel(CreditNote::class, [$id]);
    }
}
