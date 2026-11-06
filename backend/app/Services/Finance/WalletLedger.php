<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Exceptions\WalletException;
use App\Models\CustomerWallet;
use App\Models\User;
use App\Models\WalletEntry;
use App\Support\Finance\Decimal;
use App\Support\Finance\WalletMovement;
use Closure;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The only writer of `wallet_entries` and of `customer_wallets.balance`. Every
 * movement runs with the wallet's row locked (SELECT ... FOR UPDATE), so two
 * requests on one wallet queue up and the balance check always sees the other
 * one's entry. Amounts are bcmath strings; the stored balance always equals
 * the SUM of the wallet's signed entries.
 *
 * Lock order, shared by every money path: receipts (by id), then invoices (by
 * id), then the wallet, then document_sequences. A caller that needs an
 * invoice or a receipt locks it before calling lock() here, and takes a
 * document number only after.
 */
class WalletLedger
{
    /** Money an invoice owes back, moved to the wallet (credit) or taken back (debit). */
    public const RELATED_INVOICE = 'invoice';

    public const RELATED_RECEIPT = 'receipt';

    public const RELATED_VOUCHER = 'payment_voucher';

    /** A reversal of another entry of the same wallet. */
    public const RELATED_ENTRY = 'wallet_entry';

    /** The wallet balance of a customer in the workspace's currency, e.g. "120.50": the SUM of its entries. */
    public function balance(string $workspaceId, string $customerId): string
    {
        return Decimal::of(WalletEntry::query()->where('workspace_id', $workspaceId)->where('customer_id', $customerId)->sum('amount'));
    }

    /**
     * Run `$work` in a transaction with the wallet locked.
     *
     * @template T
     *
     * @param  Closure(CustomerWallet): T  $work
     * @return T
     */
    public function locked(string $workspaceId, string $customerId, Closure $work): mixed
    {
        return DB::transaction(fn (): mixed => $work($this->lock($workspaceId, $customerId)));
    }

    /**
     * Lock the wallet row for the rest of the caller's transaction, creating it on first use.
     *
     * @throws WalletException when the customer does not exist
     */
    public function lock(string $workspaceId, string $customerId): CustomerWallet
    {
        $wallet = $this->find($workspaceId, $customerId);

        if ($wallet !== null) {
            return $wallet;
        }

        // A first movement races only another first movement: insertOrIgnore keeps whichever row
        // was written first, and the locking read below then queues behind its transaction.
        $now = Carbon::now();
        CustomerWallet::query()->insertOrIgnore([
            'id' => (string) Str::uuid(),
            'workspace_id' => $workspaceId,
            'customer_id' => $customerId,
            'balance' => '0.00',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->find($workspaceId, $customerId) ?? throw WalletException::unknownCustomer();
    }

    /** Throw unless the locked wallet holds at least `$amount`. */
    public function assertCovers(CustomerWallet $wallet, string $amount): void
    {
        if (bccomp($amount, (string) $wallet->balance, 2) > 0) {
            throw WalletException::insufficient((string) $wallet->balance);
        }
    }

    /** The entry already stored under a client request key, if any. */
    public function findByKey(string $workspaceId, string $requestKey): ?WalletEntry
    {
        return WalletEntry::query()->where('workspace_id', $workspaceId)->where('request_key', $requestKey)->first();
    }

    /**
     * The entry a repeated request already wrote. Call with the wallet locked:
     * the lookup is then the transaction's first plain read, so on MariaDB it
     * sees an entry a racing repeat committed while this one waited.
     *
     * @throws WalletException when the key was used for a different operation
     */
    public function repeatOf(CustomerWallet $wallet, WalletMovement $movement): ?WalletEntry
    {
        $entry = $movement->requestKey === null ? null : $this->findByKey($wallet->workspace_id, $movement->requestKey);

        if ($entry === null) {
            return null;
        }

        $same = $entry->customer_id === $wallet->customer_id
            && $entry->type === $movement->type
            && bccomp(ltrim((string) $entry->amount, '-'), bcadd($movement->amount, '0', 2), 2) === 0
            && ($movement->relatedId === null || $entry->related_id === $movement->relatedId);

        return $same ? $entry : throw WalletException::requestKeyReused();
    }

    /**
     * Append one movement to the locked wallet and move its balance. A repeated
     * request key returns the first entry instead (check `wasRecentlyCreated`).
     *
     * @throws WalletException when the amount is not positive, the wallet cannot cover it, or the key belongs to another operation
     */
    public function append(CustomerWallet $wallet, WalletMovement $movement, ?User $actor): WalletEntry
    {
        $amount = bcadd($movement->amount, '0', 2);

        if (bccomp($amount, '0', 2) <= 0) {
            throw WalletException::invalidAmount();
        }

        $repeat = $this->repeatOf($wallet, $movement);

        if ($repeat !== null) {
            return $repeat;
        }

        if (! $movement->type->isIncoming()) {
            $this->assertCovers($wallet, $amount);
        }

        return $this->write($wallet, $movement, $movement->type->isIncoming() ? $amount : bcmul($amount, '-1', 2), $actor);
    }

    private function write(CustomerWallet $wallet, WalletMovement $movement, string $signed, ?User $actor): WalletEntry
    {
        $entry = WalletEntry::query()->create([
            'workspace_id' => $wallet->workspace_id,
            'customer_id' => $wallet->customer_id,
            'type' => $movement->type,
            'amount' => $signed,
            'method' => $movement->method,
            'reason' => $movement->reason,
            'related_type' => $movement->relatedType,
            'related_id' => $movement->relatedId,
            'request_key' => $movement->requestKey,
            'created_by' => $actor?->id,
        ]);

        $wallet->forceFill(['balance' => bcadd((string) $wallet->balance, $signed, 2)])->save();

        return $entry;
    }

    private function find(string $workspaceId, string $customerId): ?CustomerWallet
    {
        return CustomerWallet::query()->where('workspace_id', $workspaceId)->where('customer_id', $customerId)->lockForUpdate()->first();
    }
}
