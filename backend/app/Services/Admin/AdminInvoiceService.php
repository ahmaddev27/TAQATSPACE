<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Services\FileUploadService;
use App\Services\InvoiceService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;

/**
 * Super-admin invoice tracking and manual payment recording.
 *
 * Paid/unpaid transitions and receipt uploads are admin bookkeeping only —
 * there is no payment gateway. mark-paid reuses the shared InvoiceService so
 * member notifications and cache invalidation stay consistent.
 */
class AdminInvoiceService
{
    /** Eager-load chain that satisfies the admin invoice resource contract. */
    private const RELATIONS = ['subscription.member:id,name,email,phone', 'subscription.workspace:id,name,city'];

    public function __construct(
        private readonly InvoiceService $invoices,
        private readonly FileUploadService $uploads,
    ) {}

    /**
     * Filtered, paginated invoices with the member + workspace chain loaded.
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Invoice>
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        return $this->filteredQuery($filters)
            ->with(self::RELATIONS)
            ->orderByDesc('due_date')
            ->paginate($this->perPage($filters))
            ->withQueryString();
    }

    /**
     * Mark an invoice paid, reloading the contract relations for the response.
     *
     * @throws \RuntimeException when the invoice is already paid
     */
    public function markPaid(Invoice $invoice, ?Carbon $paidAt = null): Invoice
    {
        $this->invoices->markPaid($invoice, $paidAt);

        return $invoice->load(self::RELATIONS);
    }

    /**
     * Revert a paid invoice back to pending and clear its payment timestamp.
     * Idempotent for already-unpaid invoices.
     */
    public function markUnpaid(Invoice $invoice): Invoice
    {
        if ($invoice->status !== InvoiceStatus::Pending || $invoice->paid_at !== null) {
            $invoice->forceFill([
                'status' => InvoiceStatus::Pending->value,
                'paid_at' => null,
            ])->save();
        }

        return $invoice->load(self::RELATIONS);
    }

    /**
     * Store the uploaded payment receipt under receipts/{invoice}, persist its
     * path, and record the payment. Uploading a receipt is the admin's proof of
     * payment, so the invoice is marked paid in the same action (idempotent when
     * it was already paid).
     */
    public function attachReceipt(Invoice $invoice, UploadedFile $receipt, ?Carbon $paidAt = null): Invoice
    {
        $path = $this->uploads->upload(
            $receipt,
            'receipts/'.$invoice->id,
            (string) config('filesystems.media', 'public'),
            'public',
        );

        $invoice->forceFill(['receipt_path' => $path])->save();

        if ($invoice->status !== InvoiceStatus::Paid) {
            $this->invoices->markPaid($invoice, $paidAt);
        }

        return $invoice->load(self::RELATIONS);
    }

    /**
     * Filtered query (no pagination) with the member + workspace chain loaded,
     * for memory-safe cursor-based CSV export of the full result set.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<Invoice>
     */
    public function exportQuery(array $filters): Builder
    {
        return $this->filteredQuery($filters)
            ->with(self::RELATIONS)
            ->orderByDesc('due_date');
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Invoice>
     */
    private function filteredQuery(array $filters): Builder
    {
        $query = Invoice::query();

        if (! empty($filters['status']) && InvoiceStatus::tryFrom((string) $filters['status']) !== null) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['search'])) {
            $like = '%'.trim((string) $filters['search']).'%';

            $query->where(function (Builder $inner) use ($like): void {
                $inner->where('invoice_number', 'like', $like)
                    ->orWhereHas('subscription.member', function (Builder $member) use ($like): void {
                        $member->where('name', 'like', $like)
                            ->orWhere('email', 'like', $like);
                    });
            });
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function perPage(array $filters): int
    {
        $perPage = (int) ($filters['per_page'] ?? 15);

        return max(1, min($perPage, 100));
    }
}
