<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Exceptions\InvoiceLifecycleException;
use App\Exceptions\ReceiptException;
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
 * (finance v2): each collected receipt that paid only this invoice is voided
 * through ReceiptService::void, money recorded before receipts existed is
 * cleared, and the status is re-derived from what is left. Pending cheques are
 * not payments yet and stay as they are.
 */
class PaymentReversalService
{
    public function __construct(
        private readonly ReceiptRepository $receiptRepository,
        private readonly ReceiptService $receipts,
        private readonly InvoiceBalanceService $balances,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @throws InvoiceLifecycleException when credit notes leave nothing owed, or legacy payment rows are not on receipts yet
     * @throws ReceiptException when a receipt also paid another invoice
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

            foreach ($receipts as $receipt) {
                $this->receipts->void($receipt, __('messages.invoice_unpaid_receipt_void_reason'), $actor);
            }

            $this->settle($invoice, $unbacked, $receipts, $actor);
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

        if ($this->receiptRepository->anyAllocatedElsewhere($receipts->modelKeys(), $locked)) {
            throw ReceiptException::sharedAllocation();
        }

        $unbacked = bcsub((string) $locked->amount_paid, $this->receiptRepository->collectedTotalFor($locked), 2);

        if (bccomp($unbacked, '0', 2) <= 0) {
            return '0.00';
        }

        return $this->receiptRepository->hasPaymentsWithoutReceipt($locked) ? throw InvoiceLifecycleException::paymentsWithoutReceipt() : $unbacked;
    }

    /** @param  Collection<int, Receipt>  $receipts */
    private function settle(Invoice $invoice, string $unbacked, Collection $receipts, ?User $actor): void
    {
        // Re-read: the voids above moved the paid total on their own copies of the row.
        $locked = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
        $now = Carbon::now();

        if (bccomp($unbacked, '0', 2) > 0) {
            // No receipt carries this money, so there is nothing to void: it goes with the mark-unpaid itself.
            $this->balances->applyDelta($locked, bcmul($unbacked, '-1', 2), $now);
        }

        $this->balances->reconcile($locked, $now);

        $this->audit->log('finance.invoice.marked_unpaid', $locked, actor: $actor, changes: [
            'voided_receipts' => $receipts->pluck('number')->values()->all(),
            'cleared_without_receipt' => $unbacked,
            'amount_paid_after' => (string) $locked->amount_paid,
        ]);
    }
}
