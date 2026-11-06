<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Enums\PaymentMethod;
use App\Enums\WalletEntryType;
use App\Exceptions\WalletException;
use App\Models\PaymentVoucher;
use App\Models\Receipt;
use App\Models\User;
use App\Models\WalletEntry;
use App\Models\Workspace;
use App\Repositories\Eloquent\UnbilledChargeRepository;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Carbon;

/**
 * A customer's wallet and deposit, in the workspace's currency. It only records
 * money the owner handled by cash, bank or cheque; nothing here charges a card.
 * Every movement is an append-only ledger entry written by {@see WalletLedger},
 * and a balance can never go negative.
 */
class WalletService
{
    public function __construct(
        private readonly WalletLedger $ledger,
        private readonly PaymentVoucherService $vouchers,
        private readonly ReceiptService $receipts,
        private readonly UnbilledChargeRepository $customers,
        private readonly AuditLogger $audit,
    ) {}

    public function balance(Workspace $workspace, string $customerId): string
    {
        return $this->ledger->balance($workspace->id, $customerId);
    }

    /**
     * Money received from the customer and held on their wallet.
     *
     * @throws WalletException when the person is not a customer or the amount is not positive
     */
    public function receiveDeposit(Workspace $workspace, string $customerId, string $amount, PaymentMethod $method, ?string $reason, ?string $requestKey, ?User $actor): WalletEntry
    {
        if (! $this->customers->isCustomerOf($workspace, $customerId)) {
            throw WalletException::unknownCustomer();
        }

        $entry = $this->ledger->append($workspace->id, $customerId, WalletEntryType::DepositIn, $amount, $reason, null, null, $actor, $requestKey, $method->value);
        $this->auditEntry('finance.wallet.deposit_received', $entry, $actor, ['method' => $method->value]);

        return $entry;
    }

    /**
     * Pay money back to the customer: a ledger entry plus the numbered voucher.
     *
     * @throws WalletException when the wallet holds less than the amount
     */
    public function returnDeposit(Workspace $workspace, string $customerId, string $amount, PaymentMethod $method, string $reason, ?string $reference, ?Carbon $paidAt, ?string $requestKey, ?User $actor): PaymentVoucher
    {
        [$voucher, $entry] = $this->ledger->locked($customerId, function () use ($workspace, $customerId, $amount, $method, $reason, $reference, $paidAt, $requestKey, $actor): array {
            $entry = $this->ledger->append($workspace->id, $customerId, WalletEntryType::DepositReturn, $amount, $reason, null, null, $actor, $requestKey, $method->value);

            // A repeated request finds the voucher the first one issued.
            $voucher = $entry->wasRecentlyCreated
                ? $this->vouchers->issueFor($entry, $method, $reference, $paidAt, $actor)
                : PaymentVoucher::query()->where('wallet_entry_id', $entry->id)->firstOrFail();

            return [$voucher, $entry];
        });

        $this->auditEntry('finance.wallet.deposit_returned', $entry, $actor, ['voucher' => $voucher->number, 'method' => $method->value]);

        return $voucher->load('customer:id,name');
    }

    /**
     * Pay an invoice out of the wallet. The receipt is the document; its method is
     * `wallet`, so reports can tell it apart from money that came in.
     *
     * @throws WalletException when the wallet holds less than the amount
     */
    public function settleAgainstInvoice(Workspace $workspace, string $customerId, string $invoiceId, string $amount, ?string $requestKey, ?User $actor): Receipt
    {
        $amount = bcadd($amount, '0', 2);

        if (bccomp($amount, '0', 2) <= 0) {
            throw WalletException::invalidAmount();
        }

        $receipt = $this->ledger->locked($customerId, function () use ($workspace, $customerId, $invoiceId, $amount, $requestKey, $actor): Receipt {
            $repeat = $requestKey === null ? null : $this->ledger->findByKey($workspace->id, $requestKey);

            if ($repeat !== null) {
                $this->assertRepeatMatches($repeat, $customerId, $amount);

                return Receipt::query()
                    ->with('allocations.invoice:id,invoice_number,amount', 'customer:id,name')
                    ->where('workspace_id', $workspace->id)
                    ->findOrFail($repeat->related_id);
            }

            // Checked before the receipt is made, so a refused settlement never touches the invoice.
            $this->ledger->assertCovers($workspace->id, $customerId, $amount);

            $receipt = $this->receipts->create($workspace, [
                'customer_id' => $customerId,
                'method' => PaymentMethod::Wallet->value,
                'amount' => $amount,
                'allocations' => [['invoice_id' => $invoiceId, 'amount' => $amount]],
            ], $actor);

            $entry = $this->ledger->append($workspace->id, $customerId, WalletEntryType::Settlement, $amount, null, WalletLedger::RELATED_RECEIPT, $receipt->id, $actor, $requestKey);
            $this->auditEntry('finance.wallet.settled', $entry, $actor, ['receipt' => $receipt->number, 'invoice_id' => $invoiceId]);

            return $receipt;
        });

        return $receipt;
    }

    /**
     * Keep (part of) the customer's deposit; the reason is mandatory.
     *
     * @throws WalletException when the wallet holds less than the amount
     */
    public function forfeit(Workspace $workspace, string $customerId, string $amount, string $reason, ?string $requestKey, ?User $actor): WalletEntry
    {
        $entry = $this->ledger->append($workspace->id, $customerId, WalletEntryType::Forfeit, $amount, $reason, null, null, $actor, $requestKey);

        if ($entry->wasRecentlyCreated) {
            $this->audit->log('finance.wallet.forfeited', $entry, reason: $reason, actor: $actor, changes: ['amount' => $entry->amount, 'customer_id' => $customerId]);
        }

        return $entry;
    }

    /** @param  array<string, mixed>  $extra */
    private function auditEntry(string $action, WalletEntry $entry, ?User $actor, array $extra = []): void
    {
        if (! $entry->wasRecentlyCreated) {
            return;
        }

        $this->audit->log($action, $entry, actor: $actor, changes: ['amount' => $entry->amount, 'customer_id' => $entry->customer_id, ...$extra]);
    }

    private function assertRepeatMatches(WalletEntry $entry, string $customerId, string $amount): void
    {
        $same = $entry->type === WalletEntryType::Settlement
            && $entry->customer_id === $customerId
            && bccomp(ltrim((string) $entry->amount, '-'), $amount, 2) === 0;

        if (! $same) {
            throw WalletException::requestKeyReused();
        }
    }
}
