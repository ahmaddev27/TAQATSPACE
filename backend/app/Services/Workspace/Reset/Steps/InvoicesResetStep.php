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
        InvoicePayment::query()->whereIn('invoice_id', $this->invoices($workspace)->select('id'))->delete();
        $this->invoices($workspace)->delete();
    }

    public function filePaths(Workspace $workspace): array
    {
        $paymentPaths = InvoicePayment::query()
            ->whereIn('invoice_id', $this->invoices($workspace)->select('id'))
            ->pluck('receipt_path');

        $invoicePaths = $this->invoices($workspace)
            ->get(['receipt_path', 'invoice_pdf_path'])
            ->flatMap(fn (Invoice $invoice): array => [$invoice->receipt_path, $invoice->invoice_pdf_path]);

        return $paymentPaths->concat($invoicePaths)->filter()->unique()->values()->all();
    }

    /**
     * By workspace as well as by subscription: an invoice raised without a
     * subscription has only the workspace to tie it here.
     *
     * @return Builder<Invoice>
     */
    private function invoices(Workspace $workspace): Builder
    {
        return Invoice::query()->where(fn (Builder $query) => $query
            ->where('workspace_id', $workspace->id)
            ->orWhereIn('subscription_id', Subscription::query()->where('workspace_id', $workspace->id)->select('id')));
    }
}
