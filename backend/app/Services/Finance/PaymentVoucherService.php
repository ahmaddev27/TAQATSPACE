<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Enums\DocumentType;
use App\Enums\PaymentMethod;
use App\Enums\WalletEntryType;
use App\Exceptions\WalletException;
use App\Models\PaymentVoucher;
use App\Models\User;
use App\Models\WalletEntry;
use App\Models\Workspace;
use App\Services\Audit\AuditLogger;
use App\Support\Finance\WalletMovement;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Payment vouchers: the numbered document for money paid back to a customer.
 * One is issued with every deposit return; voiding one keeps it on file and
 * puts the money back on the wallet through a reversing entry. Audit entries
 * are written in the same transaction as the money.
 */
class PaymentVoucherService
{
    public function __construct(
        private readonly DocumentNumberService $numbers,
        private readonly WalletLedger $ledger,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Issue the voucher for a wallet entry that paid money out. Runs inside the
     * caller's transaction, after it locked the wallet: the number comes last.
     */
    public function issueFor(WalletEntry $entry, PaymentMethod $method, ?string $reference, ?Carbon $paidAt, ?User $actor): PaymentVoucher
    {
        $voucher = PaymentVoucher::query()->create([
            'workspace_id' => $entry->workspace_id,
            'customer_id' => $entry->customer_id,
            'number' => $this->numbers->next(Workspace::query()->findOrFail($entry->workspace_id), DocumentType::PaymentVoucher),
            'method' => $method,
            'amount' => ltrim((string) $entry->amount, '-'),
            'reason' => $entry->reason,
            'reference' => $reference,
            'wallet_entry_id' => $entry->id,
            'paid_at' => $paidAt ?? Carbon::now(),
            'created_by' => $actor?->id,
        ]);

        $this->audit->log('finance.voucher.created', $voucher, actor: $actor, changes: [
            'number' => $voucher->number,
            'method' => $method->value,
            'amount' => $voucher->amount,
        ]);

        return $voucher;
    }

    /**
     * Locks the voucher, then the customer's wallet.
     *
     * @throws WalletException when the voucher is already void, or its customer no longer exists
     */
    public function void(PaymentVoucher $voucher, string $reason, ?User $actor): PaymentVoucher
    {
        $voided = DB::transaction(function () use ($voucher, $reason, $actor): PaymentVoucher {
            $locked = PaymentVoucher::query()->lockForUpdate()->findOrFail($voucher->id);

            if ($locked->isVoided()) {
                throw WalletException::voucherVoided();
            }

            $wallet = $this->ledger->lock($locked->workspace_id, $locked->customer_id ?? throw WalletException::unknownCustomer());
            $locked->forceFill(['voided_at' => Carbon::now(), 'void_reason' => $reason])->save();
            $this->ledger->append($wallet, new WalletMovement(
                WalletEntryType::Credit,
                (string) $locked->amount,
                $reason,
                WalletLedger::RELATED_VOUCHER,
                $locked->id,
            ), $actor);

            $this->audit->log('finance.voucher.voided', $locked, reason: $reason, actor: $actor, changes: [
                'number' => $locked->number,
                'amount' => $locked->amount,
            ]);

            return $locked;
        });

        return $voided->load('customer:id,name');
    }
}
