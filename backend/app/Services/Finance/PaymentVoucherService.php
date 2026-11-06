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
use Illuminate\Support\Carbon;

/**
 * Payment vouchers: the numbered document for money paid back to a customer.
 * One is issued with every deposit return; voiding one keeps it on file and
 * puts the money back on the wallet through a reversing entry.
 */
class PaymentVoucherService
{
    public function __construct(
        private readonly DocumentNumberService $numbers,
        private readonly WalletLedger $ledger,
        private readonly AuditLogger $audit,
    ) {}

    /** Issue the voucher for a wallet entry that paid money out. Runs inside the caller's transaction. */
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
     * @throws WalletException when the voucher is already void
     */
    public function void(PaymentVoucher $voucher, string $reason, ?User $actor): PaymentVoucher
    {
        $voided = $this->ledger->locked((string) $voucher->customer_id, function () use ($voucher, $reason, $actor): PaymentVoucher {
            $locked = PaymentVoucher::query()->lockForUpdate()->findOrFail($voucher->id);

            if ($locked->isVoided()) {
                throw WalletException::voucherVoided();
            }

            $locked->forceFill(['voided_at' => Carbon::now(), 'void_reason' => $reason])->save();
            $this->ledger->append(
                $locked->workspace_id,
                (string) $locked->customer_id,
                WalletEntryType::Credit,
                (string) $locked->amount,
                $reason,
                WalletLedger::RELATED_VOUCHER,
                $locked->id,
                $actor,
            );

            return $locked;
        });

        $this->audit->log('finance.voucher.voided', $voided, reason: $reason, actor: $actor, changes: [
            'number' => $voided->number,
            'amount' => $voided->amount,
        ]);

        return $voided->load('customer:id,name');
    }
}
