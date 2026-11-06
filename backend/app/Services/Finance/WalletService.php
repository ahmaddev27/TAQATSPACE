<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\WalletEntryType;
use App\Exceptions\ReceiptException;
use App\Exceptions\WalletException;
use App\Models\CustomerWallet;
use App\Models\Invoice;
use App\Models\PaymentVoucher;
use App\Models\Receipt;
use App\Models\User;
use App\Models\WalletEntry;
use App\Models\Workspace;
use App\Notifications\InvoicePaidNotification;
use App\Repositories\Eloquent\UnbilledChargeRepository;
use App\Repositories\Eloquent\WalletRepository;
use App\Services\Audit\AuditLogger;
use App\Support\Finance\WalletMovement;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * A customer's wallet and deposit, in the workspace's currency. It only records
 * money the owner handled in cash, by card or by bank; nothing here charges a
 * card. Every movement is an append-only ledger entry written by
 * {@see WalletLedger} with the wallet locked, a balance never goes negative,
 * and each audit entry is written in the movement's own transaction, so the
 * trail and the money commit or roll back together.
 */
class WalletService
{
    public function __construct(
        private readonly WalletLedger $ledger,
        private readonly PaymentVoucherService $vouchers,
        private readonly ReceiptService $receipts,
        private readonly UnbilledChargeRepository $customers,
        private readonly WalletRepository $wallets,
        private readonly AuditLogger $audit,
    ) {}

    public function balance(Workspace $workspace, string $customerId): string
    {
        return $this->ledger->balance($workspace->id, $customerId);
    }

    /**
     * Money received from the customer and held on their wallet. Only money in
     * hand counts: a cheque could be paid out before it clears.
     *
     * @throws WalletException when the person is not a customer, the method is a cheque, or the amount is not positive
     */
    public function receiveDeposit(Workspace $workspace, string $customerId, string $amount, PaymentMethod $method, ?string $reason, ?string $requestKey, ?User $actor): WalletEntry
    {
        if (! in_array($method->value, PaymentMethod::inHandValues(), true)) {
            throw WalletException::depositMethod();
        }

        if (! $this->customers->isCustomerOf($workspace, $customerId)) {
            throw WalletException::unknownCustomer();
        }

        $movement = new WalletMovement(WalletEntryType::DepositIn, $amount, $reason, requestKey: $requestKey, method: $method->value);

        return $this->keyed($requestKey, fn (): WalletEntry => $this->ledger->locked($workspace->id, $customerId, fn (CustomerWallet $wallet): WalletEntry => $this->post(
            $wallet, $movement, $actor, 'finance.wallet.deposit_received', ['method' => $method->value],
        )));
    }

    /**
     * Pay money back to the customer: a ledger entry plus the numbered voucher.
     *
     * @throws WalletException when the wallet holds less than the amount
     */
    public function returnDeposit(Workspace $workspace, string $customerId, string $amount, PaymentMethod $method, string $reason, ?string $reference, ?Carbon $paidAt, ?string $requestKey, ?User $actor): PaymentVoucher
    {
        $this->assertKnownCustomer($workspace, $customerId);
        $movement = new WalletMovement(WalletEntryType::DepositReturn, $amount, $reason, requestKey: $requestKey, method: $method->value);

        $voucher = $this->keyed($requestKey, fn (): PaymentVoucher => $this->ledger->locked($workspace->id, $customerId, function (CustomerWallet $wallet) use ($movement, $method, $reference, $paidAt, $actor): PaymentVoucher {
            $entry = $this->ledger->append($wallet, $movement, $actor);

            // A repeated request finds the voucher the first one issued.
            if (! $entry->wasRecentlyCreated) {
                return PaymentVoucher::query()->where('wallet_entry_id', $entry->id)->firstOrFail();
            }

            // The wallet is locked already, so the voucher's number is taken after it, as everywhere.
            $voucher = $this->vouchers->issueFor($entry, $method, $reference, $paidAt, $actor);
            $this->auditEntry('finance.wallet.deposit_returned', $entry, $actor, ['voucher' => $voucher->number, 'method' => $method->value]);

            return $voucher;
        }));

        return $voucher->load('customer:id,name');
    }

    /**
     * Pay an invoice out of the wallet. The receipt is the document; its method is
     * `wallet`, so reports can tell it apart from money that came in.
     *
     * @throws WalletException when the wallet holds less than the amount
     * @throws ReceiptException when the invoice is not this customer's in this workspace
     */
    public function settleAgainstInvoice(Workspace $workspace, string $customerId, string $invoiceId, string $amount, ?string $requestKey, ?User $actor): Receipt
    {
        $amount = $this->positive($amount);
        $this->assertKnownCustomer($workspace, $customerId);

        [$receipt, $paidNow] = $this->keyed($requestKey, fn (): array => DB::transaction(
            fn (): array => $this->settleLocked($workspace, $customerId, $invoiceId, $amount, $requestKey, $actor),
        ));

        Cache::forget("workspace:{$workspace->id}:dashboard_stats");

        // Only once it has committed, and only when this settlement is what made the invoice paid.
        if ($paidNow) {
            $invoice = Invoice::query()->findOrFail($invoiceId);
            $invoice->customer?->notify(new InvoicePaidNotification($invoice));
        }

        return $receipt->load('allocations.invoice:id,invoice_number,amount', 'customer:id,name');
    }

    /**
     * Keep (part of) the customer's deposit; the reason is mandatory.
     *
     * @throws WalletException when the wallet holds less than the amount
     */
    public function forfeit(Workspace $workspace, string $customerId, string $amount, string $reason, ?string $requestKey, ?User $actor): WalletEntry
    {
        $this->assertKnownCustomer($workspace, $customerId);
        $movement = new WalletMovement(WalletEntryType::Forfeit, $amount, $reason, requestKey: $requestKey);

        return $this->keyed($requestKey, fn (): WalletEntry => $this->ledger->locked($workspace->id, $customerId, fn (CustomerWallet $wallet): WalletEntry => $this->post(
            $wallet, $movement, $actor, 'finance.wallet.forfeited',
        )));
    }

    /**
     * Undo a deposit or a forfeit recorded by mistake with the opposite entry;
     * the original stays on the ledger. A deposit can only be undone while the
     * wallet still holds it.
     *
     * @throws WalletException when the entry cannot be reversed, was reversed already, or the balance no longer covers it
     */
    public function reverseEntry(Workspace $workspace, string $customerId, string $entryId, string $reason, ?string $requestKey, ?User $actor): WalletEntry
    {
        $this->assertKnownCustomer($workspace, $customerId);

        return $this->keyed($requestKey, fn (): WalletEntry => $this->ledger->locked($workspace->id, $customerId, function (CustomerWallet $wallet) use ($workspace, $customerId, $entryId, $reason, $requestKey, $actor): WalletEntry {
            // Read after the wallet lock: every reversal of this wallet is written under it.
            $original = $this->wallets->entryOf($workspace, $customerId, $entryId);
            $type = $original->type->reversal() ?? throw WalletException::notReversible();
            $movement = new WalletMovement($type, ltrim((string) $original->amount, '-'), $reason, WalletLedger::RELATED_ENTRY, $original->id, $requestKey);

            if ($this->ledger->repeatOf($wallet, $movement) === null && $this->wallets->isReversed($original)) {
                throw WalletException::alreadyReversed();
            }

            return $this->post($wallet, $movement, $actor, 'finance.wallet.entry_reversed', [
                'reversed_entry_id' => $original->id,
                'reversed_type' => $original->type->value,
            ]);
        }));
    }

    /**
     * Lock the invoice, then the wallet, then check, then write the receipt (whose number
     * comes last): the order every money path locks in. Both locks come before the first plain
     * read, so on MariaDB the key lookup sees what a racing repeat committed meanwhile.
     *
     * @return array{0: Receipt, 1: bool} the receipt, and whether this settlement made the invoice paid
     */
    private function settleLocked(Workspace $workspace, string $customerId, string $invoiceId, string $amount, ?string $requestKey, ?User $actor): array
    {
        $invoice = $this->lockInvoice($workspace, $customerId, $invoiceId);
        $wallet = $this->ledger->lock($workspace->id, $customerId);
        $repeat = $this->ledger->repeatOf($wallet, new WalletMovement(WalletEntryType::Settlement, $amount, requestKey: $requestKey));

        if ($repeat !== null) {
            return [$this->repeatedReceipt($workspace, $repeat, $invoiceId), false];
        }

        $this->ledger->assertCovers($wallet, $amount);
        $wasPaid = $invoice->payment_status === PaymentStatus::Paid;

        $receipt = $this->receipts->create($workspace, [
            'customer_id' => $customerId,
            'method' => PaymentMethod::Wallet->value,
            'amount' => $amount,
            'allocations' => [['invoice_id' => $invoice->id, 'amount' => $amount]],
        ], $actor, notify: false);

        $settlement = new WalletMovement(WalletEntryType::Settlement, $amount, null, WalletLedger::RELATED_RECEIPT, $receipt->id, $requestKey);
        $this->post($wallet, $settlement, $actor, 'finance.wallet.settled', ['receipt' => $receipt->number, 'invoice_id' => $invoice->id]);

        $isPaid = Invoice::query()->lockForUpdate()->findOrFail($invoice->id)->payment_status === PaymentStatus::Paid;

        return [$receipt, ! $wasPaid && $isPaid];
    }

    /** @throws ReceiptException when the invoice is not the customer's in this workspace */
    private function lockInvoice(Workspace $workspace, string $customerId, string $invoiceId): Invoice
    {
        $invoice = Invoice::query()->where('workspace_id', $workspace->id)->lockForUpdate()->find($invoiceId);

        return $invoice !== null && $invoice->customer_id === $customerId ? $invoice : throw ReceiptException::invoiceMismatch();
    }

    /** The receipt a repeated settlement wrote; the key must have paid the same invoice. */
    private function repeatedReceipt(Workspace $workspace, WalletEntry $entry, string $invoiceId): Receipt
    {
        $receipt = Receipt::query()->with('allocations')->where('workspace_id', $workspace->id)->findOrFail($entry->related_id);

        return $receipt->allocations->contains('invoice_id', $invoiceId) ? $receipt : throw WalletException::requestKeyReused();
    }

    /**
     * Append a movement and, when it is new (not a repeated request), log it.
     *
     * @param  array<string, mixed>  $extra
     */
    private function post(CustomerWallet $wallet, WalletMovement $movement, ?User $actor, string $action, array $extra = []): WalletEntry
    {
        $entry = $this->ledger->append($wallet, $movement, $actor);
        $this->auditEntry($action, $entry, $actor, $extra);

        return $entry;
    }

    /** @param  array<string, mixed>  $extra */
    private function auditEntry(string $action, WalletEntry $entry, ?User $actor, array $extra = []): void
    {
        if (! $entry->wasRecentlyCreated) {
            return;
        }

        $this->audit->log($action, $entry, reason: $entry->reason, actor: $actor, changes: [
            'amount' => $entry->amount,
            'customer_id' => $entry->customer_id,
            ...$extra,
        ]);
    }

    /**
     * Run a keyed write. Two requests with one key on two wallets lock apart, so both can pass
     * the key lookup; the unique index then keeps the first, and the second is a conflict.
     *
     * @template T
     *
     * @param  Closure(): T  $work
     * @return T
     */
    private function keyed(?string $requestKey, Closure $work): mixed
    {
        try {
            return $work();
        } catch (UniqueConstraintViolationException $e) {
            throw $requestKey === null ? $e : WalletException::requestKeyReused();
        }
    }

    /** A movement other than a deposit may touch a customer of the workspace, or someone with wallet history there. */
    private function assertKnownCustomer(Workspace $workspace, string $customerId): void
    {
        if (! $this->wallets->isKnownCustomer($workspace, $customerId)) {
            throw WalletException::unknownCustomer();
        }
    }

    private function positive(string $amount): string
    {
        $amount = bcadd($amount, '0', 2);

        return bccomp($amount, '0', 2) > 0 ? $amount : throw WalletException::invalidAmount();
    }
}
