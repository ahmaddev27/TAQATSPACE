<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Enums\WalletEntryType;
use App\Exceptions\WalletException;
use App\Models\User;
use App\Models\WalletEntry;
use Closure;
use Illuminate\Support\Facades\DB;

/**
 * The only writer of `wallet_entries`. Every append runs in a transaction that
 * holds a row lock on the customer, so two requests for the same wallet queue
 * up and the balance check always sees the other one's entry. Amounts are
 * bcmath strings; the balance is the SUM of the signed entries.
 */
class WalletLedger
{
    public const RELATED_CREDIT_NOTE = 'credit_note';

    public const RELATED_RECEIPT = 'receipt';

    public const RELATED_VOUCHER = 'payment_voucher';

    /** The wallet balance of a customer in the workspace's currency, e.g. "120.50". */
    public function balance(string $workspaceId, string $customerId): string
    {
        $sum = WalletEntry::query()->where('workspace_id', $workspaceId)->where('customer_id', $customerId)->sum('amount');

        return number_format((float) $sum, 2, '.', '');
    }

    /**
     * Run `$work` in a transaction with the customer's wallet locked. Use it when a
     * balance check must be made before other work that ends in an append.
     *
     * @template T
     *
     * @param  Closure(): T  $work
     * @return T
     */
    public function locked(string $customerId, Closure $work): mixed
    {
        return DB::transaction(function () use ($customerId, $work): mixed {
            $this->lock($customerId);

            return $work();
        });
    }

    /** Throw unless the wallet holds at least `$amount`. Call inside {@see locked()}. */
    public function assertCovers(string $workspaceId, string $customerId, string $amount): void
    {
        $balance = $this->balance($workspaceId, $customerId);

        if (bccomp($amount, $balance, 2) > 0) {
            throw WalletException::insufficient($balance);
        }
    }

    /** The entry already stored under a client request key, if any. */
    public function findByKey(string $workspaceId, string $requestKey): ?WalletEntry
    {
        return WalletEntry::query()->where('workspace_id', $workspaceId)->where('request_key', $requestKey)->first();
    }

    /**
     * Append one entry. `$amount` is the positive size of the movement; the stored
     * sign follows the type. A repeated `$requestKey` returns the first entry
     * instead of writing a second one (check `wasRecentlyCreated`).
     *
     * @throws WalletException when the amount is not positive, the wallet cannot cover it, or the key belongs to another operation
     */
    public function append(
        string $workspaceId,
        string $customerId,
        WalletEntryType $type,
        string $amount,
        ?string $reason,
        ?string $relatedType,
        ?string $relatedId,
        ?User $actor,
        ?string $requestKey = null,
        ?string $method = null,
    ): WalletEntry {
        $amount = bcadd($amount, '0', 2);

        if (bccomp($amount, '0', 2) <= 0) {
            throw WalletException::invalidAmount();
        }

        return $this->locked($customerId, function () use ($workspaceId, $customerId, $type, $amount, $reason, $relatedType, $relatedId, $actor, $requestKey, $method): WalletEntry {
            $existing = $requestKey === null ? null : $this->findByKey($workspaceId, $requestKey);

            if ($existing !== null) {
                return $this->assertSameOperation($existing, $customerId, $type, $amount);
            }

            if (! $type->isIncoming()) {
                $this->assertCovers($workspaceId, $customerId, $amount);
            }

            return WalletEntry::query()->create([
                'workspace_id' => $workspaceId,
                'customer_id' => $customerId,
                'type' => $type,
                'amount' => $type->isIncoming() ? $amount : bcmul($amount, '-1', 2),
                'method' => $method,
                'reason' => $reason,
                'related_type' => $relatedType,
                'related_id' => $relatedId,
                'request_key' => $requestKey,
                'created_by' => $actor?->id,
            ]);
        });
    }

    private function lock(string $customerId): void
    {
        // The customer row is the lock: a wallet that has no entry yet has no row of its own to lock.
        if (User::query()->whereKey($customerId)->lockForUpdate()->value('id') === null) {
            throw WalletException::unknownCustomer();
        }
    }

    private function assertSameOperation(WalletEntry $entry, string $customerId, WalletEntryType $type, string $amount): WalletEntry
    {
        $same = $entry->customer_id === $customerId
            && $entry->type === $type
            && bccomp(ltrim((string) $entry->amount, '-'), $amount, 2) === 0;

        return $same ? $entry : throw WalletException::requestKeyReused();
    }
}
