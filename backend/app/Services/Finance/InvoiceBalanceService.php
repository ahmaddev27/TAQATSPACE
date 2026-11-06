<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Enums\ChequeStatus;
use App\Enums\DocStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Exceptions\InvoiceLifecycleException;
use App\Exceptions\ReceiptException;
use App\Models\Invoice;
use App\Models\Receipt;
use App\Models\ReceiptAllocation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The money side of an invoice as receipts move it: which invoices may take a
 * payment, how much is still open on them, and keeping `amount_paid` /
 * `payment_status` in step. Every method expects to run inside the caller's
 * transaction; the invoice rows are locked here.
 */
class InvoiceBalanceService
{
    /**
     * Lock the invoices (in id order, so two allocations never deadlock) and
     * check each one can take this receipt's money.
     *
     * @param  array<int, string>  $invoiceIds
     * @return Collection<string, Invoice>
     *
     * @throws InvoiceLifecycleException when an invoice is a draft or void
     * @throws ReceiptException when an invoice belongs to another workspace or customer
     */
    public function lockPayable(Receipt $receipt, array $invoiceIds): Collection
    {
        $invoices = Invoice::query()->whereIn('id', $invoiceIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');

        if ($invoices->count() !== count(array_unique($invoiceIds))) {
            throw ReceiptException::invoiceMismatch();
        }

        foreach ($invoices as $invoice) {
            $this->assertCanTake($receipt, $invoice);
        }

        return $invoices;
    }

    /**
     * Money is never applied to a draft or void invoice, even by a cheque that
     * clears later.
     *
     * @param  Collection<string, Invoice>  $invoices
     *
     * @throws InvoiceLifecycleException when any invoice is not issued
     */
    public function assertAllIssued(Collection $invoices): void
    {
        foreach ($invoices as $invoice) {
            if ($invoice->doc_status !== DocStatus::Issued) {
                throw InvoiceLifecycleException::notPayable();
            }
        }
    }

    /**
     * What each invoice still has open once uncleared cheques are counted as
     * spoken for, so two cheques can never both claim the same balance.
     *
     * @param  Collection<string, Invoice>  $invoices
     * @return array<string, string> invoice id => outstanding amount
     */
    public function outstanding(Collection $invoices): array
    {
        $held = $this->heldByPendingCheques($invoices->keys()->all());
        $open = [];

        foreach ($invoices as $id => $invoice) {
            $balance = bcsub($this->unpaid($invoice), $held[$id] ?? '0.00', 2);
            $open[$id] = bccomp($balance, '0', 2) > 0 ? $balance : '0.00';
        }

        return $open;
    }

    /** What the customer still owes: the invoice total less credit notes and money collected. */
    public function unpaid(Invoice $invoice): string
    {
        $balance = bcsub($this->payable($invoice), (string) $invoice->amount_paid, 2);

        return bccomp($balance, '0', 2) > 0 ? $balance : '0.00';
    }

    /** What the invoice asks for once credit notes are deducted; zero when fully credited. */
    public function payable(Invoice $invoice): string
    {
        return bcsub((string) $invoice->amount, (string) $invoice->credited_amount, 2);
    }

    /**
     * What a "pay what is left" action may take from a locked invoice: what is
     * owed and not already promised by a pending cheque. Null when nothing is
     * owed at all (credit notes or earlier payments cover it), which the caller
     * settles with reconcile() instead of paying.
     *
     * @throws ReceiptException when pending cheques hold everything that is owed
     */
    public function openToPay(Invoice $locked): ?string
    {
        $open = $this->outstanding(new Collection([$locked->id => $locked]))[$locked->id];

        if (bccomp($open, '0', 2) > 0) {
            return $open;
        }

        return bccomp($this->unpaid($locked), '0', 2) > 0 ? throw ReceiptException::nothingOpen() : null;
    }

    /**
     * The one step that derives a locked invoice's payment status from its money:
     * paid against payable. Pending cheques are not money yet, so they never
     * change the status; they only limit what openToPay() lets an action take.
     * Every action that used to force a status (mark paid, mark unpaid, approve
     * a receipt) ends here instead.
     */
    public function reconcile(Invoice $locked, Carbon $at): void
    {
        $this->settle($locked, (string) $locked->amount_paid, (string) $locked->credited_amount, $at);
    }

    /**
     * What the customer is owed back on this invoice right now: the money paid
     * above what is still payable after credit notes. It is derived on every
     * read and never stored, so it follows a bounced cheque or a voided receipt.
     *
     * @return string decimal with two places, never negative
     */
    public function refundDue(Invoice $invoice): string
    {
        $payable = bcsub((string) $invoice->amount, (string) $invoice->credited_amount, 2);
        $overpaid = bcsub((string) $invoice->amount_paid, $payable, 2);

        return bccomp($overpaid, '0', 2) > 0 ? $overpaid : '0.00';
    }

    /**
     * What uncleared cheques are holding on each invoice.
     *
     * @param  array<int, string>  $invoiceIds
     * @return array<string, string> invoice id => held amount
     */
    public function held(array $invoiceIds): array
    {
        return $this->heldByPendingCheques($invoiceIds);
    }

    /** Move an invoice's paid total by `$delta` (negative to take money back) and re-derive its status. */
    public function applyDelta(Invoice $invoice, string $delta, Carbon $at): void
    {
        $paid = bcadd((string) $invoice->amount_paid, $delta, 2);

        $this->settle($invoice, bccomp($paid, '0', 2) < 0 ? '0.00' : $paid, (string) $invoice->credited_amount, $at);
    }

    /** Add a credit note to an invoice: its total stays, what is payable shrinks. */
    public function applyCredit(Invoice $invoice, string $credit, Carbon $at): void
    {
        $this->settle($invoice, (string) $invoice->amount_paid, bcadd((string) $invoice->credited_amount, $credit, 2), $at);
    }

    private function settle(Invoice $invoice, string $paid, string $credited, Carbon $at): void
    {
        $status = $this->statusFor($paid, bcsub((string) $invoice->amount, $credited, 2));

        $invoice->forceFill([
            'amount_paid' => $paid,
            'credited_amount' => $credited,
            'payment_status' => $status,
            'paid_at' => $status === PaymentStatus::Paid ? ($invoice->paid_at ?? $at) : null,
        ])->save();
    }

    private function assertCanTake(Receipt $receipt, Invoice $invoice): void
    {
        if ($invoice->doc_status !== DocStatus::Issued) {
            throw InvoiceLifecycleException::notPayable();
        }

        if ($invoice->workspace_id !== $receipt->workspace_id || $invoice->customer_id !== $receipt->customer_id) {
            throw ReceiptException::invoiceMismatch();
        }
    }

    private function statusFor(string $paid, string $amount): PaymentStatus
    {
        return match (true) {
            bccomp($paid, $amount, 2) >= 0 => PaymentStatus::Paid,
            bccomp($paid, '0', 2) > 0 => PaymentStatus::Partial,
            default => PaymentStatus::Unpaid,
        };
    }

    /**
     * @param  array<int, string>  $invoiceIds
     * @return array<string, string>
     */
    private function heldByPendingCheques(array $invoiceIds): array
    {
        $held = [];

        $rows = ReceiptAllocation::query()
            ->selectRaw('receipt_allocations.invoice_id, SUM(receipt_allocations.amount) as held')
            ->join('receipts', 'receipts.id', '=', 'receipt_allocations.receipt_id')
            ->whereIn('receipt_allocations.invoice_id', $invoiceIds)
            ->whereNull('receipts.voided_at')
            ->where('receipts.method', PaymentMethod::Cheque->value)
            ->whereIn('receipts.cheque_status', [ChequeStatus::Received->value, ChequeStatus::Deposited->value])
            ->groupBy('receipt_allocations.invoice_id')
            ->get();

        foreach ($rows as $row) {
            $held[(string) $row->invoice_id] = bcadd((string) $row->held, '0', 2);
        }

        return $held;
    }
}
