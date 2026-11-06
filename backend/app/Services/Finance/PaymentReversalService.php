<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Enums\ReceiptSource;
use App\Exceptions\InvoiceLifecycleException;
use App\Exceptions\ReceiptException;
use App\Exceptions\WalletException;
use App\Models\Invoice;
use App\Models\Receipt;
use App\Models\User;
use App\Repositories\Eloquent\ReceiptRepository;
use App\Services\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Takes the payments back off an invoice that was marked paid by mistake
 * (finance v2). Only receipts a pay action on the invoice wrote are voided,
 * through ReceiptService::void, and only when all their money is on this
 * invoice; money recorded before receipts existed is cleared; the status is
 * re-derived from what is left. Anything the workspace recorded itself (the
 * receipts page, a cheque) is refused: only the workspace may void it.
 * Pending cheques are not payments yet and stay as they are.
 */
class PaymentReversalService
{
    public function __construct(
        private readonly ReceiptRepository $receiptRepository,
        private readonly ReceiptService $receipts,
        private readonly InvoiceBalanceService $balances,
        private readonly InvoiceRefundWallet $refunds,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @throws InvoiceLifecycleException when credit notes leave nothing owed, or legacy payment rows are not on receipts yet
     * @throws ReceiptException when a receipt was recorded by the workspace, or holds money beyond this invoice
     * @throws WalletException (409) when the owed-back money the payment put on the wallet was already used
     */
    public function reverse(Invoice $invoice, ?User $actor): void
    {
        // Read before the transaction: inside it, a plain SELECT ahead of the locks would fix the
        // MariaDB snapshot early and the checks below could miss a change committed while we waited.
        $candidates = $this->receiptRepository->collectedIdsFor($invoice);

        DB::transaction(function () use ($invoice, $candidates, $actor): void {
            // Receipts first, then the invoice: the order void, allocate and cheque changes lock in.
            $receipts = $this->receiptRepository->lockByIds($candidates)->filter(static fn (Receipt $receipt): bool => $receipt->isCollected());
            $locked = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
            $unbacked = $this->assertReversible($locked, $receipts);
            $before = $this->moneyState($locked);

            foreach ($receipts as $receipt) {
                $this->receipts->void($receipt, __('messages.invoice_unpaid_receipt_void_reason'), $actor);
            }

            $this->settle($invoice, $unbacked, $receipts, $actor, $before);
        });
    }

    /**
     * @param  Collection<int, Receipt>  $receipts
     * @return string the paid amount no live receipt carries (money recorded before finance v2)
     */
    private function assertReversible(Invoice $locked, Collection $receipts): string
    {
        if (bccomp($this->balances->payable($locked), '0', 2) <= 0) {
            throw InvoiceLifecycleException::nothingOwedCannotUnpaid();
        }

        $this->assertOnlyPayActionReceipts($locked, $receipts);

        $unbacked = bcsub((string) $locked->amount_paid, $this->receiptRepository->collectedTotalFor($locked), 2);

        if (bccomp($unbacked, '0', 2) <= 0) {
            return '0.00';
        }

        return $this->receiptRepository->hasPaymentsWithoutReceipt($locked) ? throw InvoiceLifecycleException::paymentsWithoutReceipt() : $unbacked;
    }

    /**
     * A receipt the workspace recorded is its document to void. One that also
     * pays another invoice, or keeps money on account, would take that money
     * back too, so it is refused even when a pay action wrote it.
     *
     * @param  Collection<int, Receipt>  $receipts
     */
    private function assertOnlyPayActionReceipts(Invoice $locked, Collection $receipts): void
    {
        if ($receipts->contains(static fn (Receipt $receipt): bool => $receipt->source !== ReceiptSource::InvoicePayment)) {
            throw ReceiptException::recordedByWorkspace();
        }

        $onInvoice = $this->receiptRepository->allocatedTo($receipts->modelKeys(), $locked);

        foreach ($receipts as $receipt) {
            if (bccomp($onInvoice[$receipt->id] ?? '0.00', (string) $receipt->amount, 2) !== 0) {
                throw ReceiptException::sharedAllocation();
            }
        }
    }

    /**
     * @param  Collection<int, Receipt>  $receipts
     * @param  array{status: string, payment_status: string, paid_at: string|null, amount_paid: string}  $before
     */
    private function settle(Invoice $invoice, string $unbacked, Collection $receipts, ?User $actor, array $before): void
    {
        // Re-read: the voids above moved the paid total on their own copies of the row.
        $locked = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
        $now = Carbon::now();

        if (bccomp($unbacked, '0', 2) > 0) {
            // No receipt carries this money, so there is nothing to void: it goes with the mark-unpaid itself.
            $this->balances->applyDelta($locked, bcmul($unbacked, '-1', 2), $now);
        }

        $this->balances->reconcile($locked, $now);
        // Each void above synced its own change; this covers the money cleared without a receipt.
        $this->refunds->sync($locked, $actor, 'marked unpaid');

        if ($receipts->isEmpty() && $this->moneyState($locked) === $before) {
            return;
        }

        $this->audit->log('finance.invoice.marked_unpaid', $locked, actor: $actor, changes: [
            'voided_receipts' => $receipts->pluck('number')->values()->all(),
            'cleared_without_receipt' => $unbacked,
            'status_before' => $before['status'],
            'paid_at_before' => $before['paid_at'],
            'amount_paid_before' => $before['amount_paid'],
            'amount_paid_after' => (string) $locked->amount_paid,
        ]);
    }

    /** @return array{status: string, payment_status: string, paid_at: string|null, amount_paid: string} */
    private function moneyState(Invoice $invoice): array
    {
        return [
            'status' => $invoice->status->value,
            'payment_status' => $invoice->payment_status->value,
            'paid_at' => $invoice->paid_at?->toIso8601String(),
            'amount_paid' => (string) $invoice->amount_paid,
        ];
    }
}
