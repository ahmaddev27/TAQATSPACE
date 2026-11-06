<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\WorkspaceFeature;
use App\Models\Invoice;
use App\Models\User;
use App\Services\FeatureFlagService;
use App\Services\FileUploadService;
use App\Services\Finance\PaymentReversalService;
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
    /**
     * Eager-load chain that satisfies the admin invoice resource contract.
     * payments.receipt: with finance v2 the proof is on the receipt, behind a signed link.
     */
    private const RELATIONS = ['subscription.member:id,name,email,phone', 'subscription.workspace:id,name,city', 'payments.receipt'];

    public function __construct(
        private readonly InvoiceService $invoices,
        private readonly FileUploadService $uploads,
        private readonly FeatureFlagService $features,
        private readonly PaymentReversalService $reversals,
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
     * Mark an invoice paid, optionally attaching the payment receipt in the same
     * step, and reload the contract relations for the response.
     *
     * Finance v2 on: the payment is a receipt for what is left, so a pending
     * cheque can leave the invoice partially paid. A proof makes it a transfer,
     * goes on that receipt and is removed again if the payment fails; without
     * one, `$method` applies (cash unless given).
     *
     * Finance v2 off, as before: the receipt is stored first so a storage
     * failure aborts before the invoice flips to paid.
     *
     * @throws \RuntimeException when the invoice is already paid
     */
    public function markPaid(Invoice $invoice, ?Carbon $paidAt = null, ?UploadedFile $receipt = null, ?PaymentMethod $method = null): Invoice
    {
        if ($this->financeV2($invoice)) {
            $receipt === null
                ? $this->invoices->markPaid($invoice, $paidAt, $method)
                : $this->invoices->recordPaymentWithReceipt($invoice, $receipt, $paidAt);

            return $invoice->load(self::RELATIONS);
        }

        if ($receipt !== null) {
            $invoice->forceFill(['receipt_path' => $this->storeReceipt($invoice, $receipt)])->save();
        }

        $this->invoices->markPaid($invoice, $paidAt);

        return $invoice->load(self::RELATIONS);
    }

    /**
     * Revert a paid invoice back to pending and clear its payment timestamp.
     * Idempotent for already-unpaid invoices.
     *
     * Finance v2 on: the money lives on receipts, so the receipts that paid only
     * this invoice are voided and the status follows the money that is left
     * (PaymentReversalService). Finance v2 off: unchanged, the paid amount and
     * the payment rows stay as they were.
     */
    public function markUnpaid(Invoice $invoice, ?User $actor = null): Invoice
    {
        if ($this->financeV2($invoice)) {
            $this->reversals->reverse($invoice, $actor);

            return $invoice->refresh()->load(self::RELATIONS);
        }

        if ($invoice->status !== InvoiceStatus::Pending || $invoice->paid_at !== null) {
            $invoice->forceFill([
                'status' => InvoiceStatus::Pending->value,
                'paid_at' => null,
            ])->save();
        }

        return $invoice->load(self::RELATIONS);
    }

    /**
     * Store (or replace) the uploaded payment receipt and record the payment.
     * Uploading a receipt is the admin's proof of payment, so the invoice is
     * marked paid in the same action (idempotent when it was already paid) —
     * this endpoint also serves to replace a receipt on an already-paid invoice.
     * With finance v2 on, an invoice that is not paid yet is paid exactly as
     * markPaid() with a receipt pays it.
     */
    public function attachReceipt(Invoice $invoice, UploadedFile $receipt, ?Carbon $paidAt = null): Invoice
    {
        if ($this->financeV2($invoice) && $invoice->status !== InvoiceStatus::Paid) {
            return $this->markPaid($invoice, $paidAt, $receipt);
        }

        $invoice->forceFill(['receipt_path' => $this->storeReceipt($invoice, $receipt)])->save();

        if ($invoice->status !== InvoiceStatus::Paid) {
            $this->invoices->markPaid($invoice, $paidAt);
        }

        return $invoice->load(self::RELATIONS);
    }

    private function financeV2(Invoice $invoice): bool
    {
        return $invoice->workspace_id !== null
            && $this->features->isEnabled($invoice->workspace_id, WorkspaceFeature::FinanceV2);
    }

    /**
     * Persist an uploaded receipt under receipts/{invoice} on the media disk and
     * return its stored path. Single source of truth for receipt storage so the
     * mark-paid and standalone upload paths stay consistent.
     */
    private function storeReceipt(Invoice $invoice, UploadedFile $receipt): string
    {
        return $this->uploads->upload(
            $receipt,
            'receipts/'.$invoice->id,
            (string) config('filesystems.media', 'public'),
            'public',
        );
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

        // Inclusive due-date range; either bound may be supplied on its own.
        if (! empty($filters['date_from'])) {
            $query->whereDate('due_date', '>=', (string) $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('due_date', '<=', (string) $filters['date_to']);
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
