<?php

declare(strict_types=1);

namespace App\Services\Workspace\Reset\Steps;

use App\Contracts\WorkspaceResetStep;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Subscription;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;

/** Invoices and their payment ledger, plus the receipt / PDF files they reference. */
final class InvoicesResetStep implements WorkspaceResetStep
{
    public function key(): string
    {
        return 'invoices';
    }

    public function reset(Workspace $workspace): void
    {
        InvoicePayment::query()->whereIn('invoice_id', $this->invoiceIds($workspace))->delete();
        Invoice::query()->whereIn('subscription_id', $this->subscriptionIds($workspace))->delete();
    }

    public function filePaths(Workspace $workspace): array
    {
        $paymentPaths = InvoicePayment::query()
            ->whereIn('invoice_id', $this->invoiceIds($workspace))
            ->pluck('receipt_path');

        $invoicePaths = Invoice::query()
            ->whereIn('subscription_id', $this->subscriptionIds($workspace))
            ->get(['receipt_path', 'invoice_pdf_path'])
            ->flatMap(fn (Invoice $invoice): array => [$invoice->receipt_path, $invoice->invoice_pdf_path]);

        return $paymentPaths->concat($invoicePaths)->filter()->unique()->values()->all();
    }

    /** @return Builder<Subscription> */
    private function subscriptionIds(Workspace $workspace): Builder
    {
        return Subscription::query()->where('workspace_id', $workspace->id)->select('id');
    }

    /** @return Builder<Invoice> */
    private function invoiceIds(Workspace $workspace): Builder
    {
        return Invoice::query()->whereIn('subscription_id', $this->subscriptionIds($workspace))->select('id');
    }
}
