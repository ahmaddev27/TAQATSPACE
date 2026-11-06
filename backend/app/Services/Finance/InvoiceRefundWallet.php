<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Enums\WalletEntryType;
use App\Exceptions\WalletException;
use App\Models\CustomerWallet;
use App\Models\Invoice;
use App\Models\User;
use App\Repositories\Eloquent\WalletRepository;
use App\Services\Audit\AuditLogger;
use App\Support\Finance\WalletMovement;

/**
 * Keeps one invariant per invoice: what it has moved to the customer's wallet
 * equals what it owes back (InvoiceBalanceService::refundDue), after every
 * commit. A credit note over money already paid moves the difference in; a
 * receipt void, a cheque bounce or a mark-unpaid that takes the payment away
 * takes it back out. `invoices.moved_to_wallet` is the running total of the
 * invoice's own wallet entries, so the check needs only the invoice row the
 * caller has locked: no locking range read over the ledger, whose gap locks
 * would deadlock two invoices syncing at once.
 */
class InvoiceRefundWallet
{
    public function __construct(
        private readonly WalletLedger $ledger,
        private readonly InvoiceBalanceService $balances,
        private readonly WalletRepository $wallets,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Call with the invoice row locked, after any change to its amount_paid or
     * credited_amount, and before the transaction takes a document number: the
     * wallet lock must come before the number. Posts nothing when the two agree,
     * so a second call is a no-op and takes no wallet lock.
     *
     * @param  string|null  $cause  what changed the invoice, for the ledger line (e.g. "receipt RC-1 voided")
     *
     * @throws WalletException (409) when money has to come back off the wallet and it no longer holds it
     */
    public function sync(Invoice $locked, ?User $actor, ?string $cause = null): void
    {
        if ($locked->customer_id === null || $locked->workspace_id === null) {
            return;
        }

        $due = $this->balances->refundDue($locked);
        $moved = bcadd((string) $locked->moved_to_wallet, '0', 2);
        $delta = bcsub($due, $moved, 2);

        if (bccomp($delta, '0', 2) === 0) {
            return;
        }

        $wallet = $this->ledger->lock($locked->workspace_id, $locked->customer_id);
        $this->move($wallet, $locked, $delta, $this->reason($locked, $cause), $actor);
        $locked->forceFill(['moved_to_wallet' => $due])->save();
    }

    /** @param  iterable<Invoice>  $invoices  rows the caller has locked, in id order */
    public function syncAll(iterable $invoices, ?User $actor, ?string $cause = null): void
    {
        foreach ($invoices as $invoice) {
            $this->sync($invoice, $actor, $cause);
        }
    }

    private function move(CustomerWallet $wallet, Invoice $invoice, string $delta, string $reason, ?User $actor): void
    {
        $incoming = bccomp($delta, '0', 2) > 0;
        $amount = $incoming ? $delta : bcmul($delta, '-1', 2);

        if (! $incoming && bccomp($amount, (string) $wallet->balance, 2) > 0) {
            // The balance never goes negative: the owner voids what used the money, then retries.
            throw WalletException::refundSpent($amount, (string) $wallet->balance, $this->wallets->spentBy($wallet->workspace_id, $wallet->customer_id));
        }

        $type = $incoming ? WalletEntryType::Credit : WalletEntryType::Debit;
        $entry = $this->ledger->append($wallet, new WalletMovement($type, $amount, $reason, WalletLedger::RELATED_INVOICE, $invoice->id), $actor);

        $this->audit->log($incoming ? 'finance.wallet.refund_credited' : 'finance.wallet.refund_debited', $entry, reason: $reason, actor: $actor, changes: [
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'amount' => $entry->amount,
            'moved_to_wallet_before' => bcadd((string) $invoice->moved_to_wallet, '0', 2),
            'owed_back' => $this->balances->refundDue($invoice),
        ]);
    }

    private function reason(Invoice $invoice, ?string $cause): string
    {
        $base = 'Owed back on invoice '.($invoice->invoice_number ?? $invoice->id);

        return $cause === null ? $base : $base.': '.$cause;
    }
}
