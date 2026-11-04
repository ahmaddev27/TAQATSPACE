<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\DocStatus;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Enums\WorkspaceFeature;
use App\Exceptions\InvoiceLifecycleException;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Services\FeatureFlagService;
use App\Support\Finance\InvoiceStatusMapper;

/**
 * Enforces BR-FIN-01 (an issued invoice's money never changes) and keeps the
 * legacy `status` in step with `doc_status` / `payment_status`.
 */
class InvoiceImmutabilityObserver
{
    /** Fields frozen once an invoice leaves draft. */
    private const FROZEN = ['amount', 'subtotal', 'tax_rate', 'tax_amount', 'total', 'period_start', 'period_end', 'issued_at'];

    public function __construct(private readonly FeatureFlagService $features) {}

    public function creating(Invoice $invoice): void
    {
        $this->stampOwnership($invoice);

        if ($invoice->doc_status === null) {
            [$doc, $payment] = InvoiceStatusMapper::fromLegacy($invoice->status ?? InvoiceStatus::Pending);
            $invoice->doc_status = $doc;
            $invoice->payment_status ??= $payment;
        }

        $invoice->payment_status ??= PaymentStatus::Unpaid;
        $invoice->status ??= InvoiceStatusMapper::toLegacy($invoice->doc_status, $invoice->payment_status);

        if ($invoice->doc_status !== DocStatus::Draft && $invoice->total === null) {
            $invoice->subtotal = $invoice->total = $invoice->amount;
            $invoice->issued_at ??= now();
        }
    }

    public function updating(Invoice $invoice): void
    {
        $this->guardFrozenFields($invoice);
        $this->syncStatuses($invoice);
    }

    public function deleting(Invoice $invoice): void
    {
        if ($invoice->doc_status !== DocStatus::Draft && $this->financeV2Enabled($invoice)) {
            throw InvoiceLifecycleException::immutable();
        }
    }

    private function guardFrozenFields(Invoice $invoice): void
    {
        $original = DocStatus::tryFrom((string) $invoice->getRawOriginal('doc_status'));

        if ($original === null || $original === DocStatus::Draft) {
            return;
        }

        $allowedTransition = $invoice->doc_status === $original
            || ($original === DocStatus::Issued && $invoice->doc_status === DocStatus::Void);

        $numberChanged = $invoice->getRawOriginal('invoice_number') !== null && $invoice->isDirty('invoice_number');

        if (! $allowedTransition || $numberChanged || $invoice->isDirty(self::FROZEN)) {
            throw InvoiceLifecycleException::immutable();
        }
    }

    private function syncStatuses(Invoice $invoice): void
    {
        $statusChanged = $invoice->isDirty('status');
        $pairChanged = $invoice->isDirty(['doc_status', 'payment_status']);

        if ($statusChanged && ! $pairChanged) {
            $this->syncFromLegacy($invoice);
        } elseif ($pairChanged && ! $statusChanged) {
            $invoice->status = InvoiceStatusMapper::toLegacy($invoice->doc_status, $invoice->payment_status, $invoice->status);
        }
    }

    private function syncFromLegacy(Invoice $invoice): void
    {
        [$doc, $payment] = InvoiceStatusMapper::fromLegacy($invoice->status);

        $invoice->payment_status = $payment;

        if ($invoice->doc_status !== DocStatus::Draft && $invoice->doc_status !== DocStatus::Void) {
            $invoice->doc_status = $doc;
            $invoice->voided_at ??= $doc === DocStatus::Void ? now() : null;
        }
    }

    private function stampOwnership(Invoice $invoice): void
    {
        if ($invoice->workspace_id !== null || $invoice->subscription_id === null) {
            return;
        }

        $subscription = Subscription::query()->find($invoice->subscription_id, ['id', 'workspace_id', 'member_id']);

        $invoice->workspace_id = $subscription?->workspace_id;
        $invoice->customer_id ??= $subscription?->member_id;
    }

    private function financeV2Enabled(Invoice $invoice): bool
    {
        return $invoice->workspace_id !== null
            && $this->features->isEnabled($invoice->workspace_id, WorkspaceFeature::FinanceV2);
    }
}
