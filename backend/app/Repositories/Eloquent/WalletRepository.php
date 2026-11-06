<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Enums\PaymentMethod;
use App\Models\PaymentVoucher;
use App\Models\Receipt;
use App\Models\User;
use App\Models\WalletEntry;
use App\Models\Workspace;
use App\Services\Finance\WalletLedger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Wallet reads. Every query names the workspace, so another workspace's
 * entries and customers are never returned.
 */
class WalletRepository
{
    /** How many documents a "money already used" refusal names. */
    private const SPENT_BY_LIMIT = 5;

    public function __construct(private readonly UnbilledChargeRepository $customers) {}

    /**
     * One row per customer that has ever had a wallet entry, newest activity first.
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, WalletEntry>
     */
    public function paginateBalances(Workspace $workspace, array $filters): LengthAwarePaginator
    {
        $query = WalletEntry::query()
            ->selectRaw('customer_id, SUM(amount) as balance, MAX(created_at) as last_entry_at')
            ->where('workspace_id', $workspace->id)
            ->whereNotNull('customer_id');

        if (! empty($filters['search'])) {
            $term = '%'.addcslashes((string) $filters['search'], '%_\\').'%';
            $query->whereIn('customer_id', User::query()->select('id')->where('name', 'like', $term));
        }

        $perPage = max(1, min((int) ($filters['per_page'] ?? 50), 200));
        $page = $query->groupBy('customer_id')->orderByDesc('last_entry_at')->orderBy('customer_id')->paginate($perPage)->withQueryString();

        $names = User::query()->whereIn('id', $page->pluck('customer_id'))->pluck('name', 'id');
        $page->getCollection()->each(static function (WalletEntry $row) use ($names): void {
            $row->setAttribute('customer_name', $names[$row->customer_id] ?? null);
        });

        return $page;
    }

    /**
     * A customer's entries, newest first, each marked when a later entry reversed it.
     *
     * @return LengthAwarePaginator<int, WalletEntry>
     */
    public function paginateEntries(Workspace $workspace, string $customerId, int $perPage): LengthAwarePaginator
    {
        $page = WalletEntry::query()
            ->with('creator:id,name')
            ->where('workspace_id', $workspace->id)
            ->where('customer_id', $customerId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(max(1, min($perPage, 200)))
            ->withQueryString();

        $reversed = $this->reversedAmong($workspace->id, $page->getCollection()->modelKeys());
        $page->getCollection()->each(static function (WalletEntry $entry) use ($reversed): void {
            $entry->reversed = isset($reversed[$entry->id]);
        });

        return $page;
    }

    /** A customer of the workspace, or one whose wallet has history there. */
    public function customer(Workspace $workspace, string $id): User
    {
        $user = User::query()->select('id', 'name')->find($id);

        return $user !== null && $this->isKnownCustomer($workspace, $id)
            ? $user
            : throw (new ModelNotFoundException)->setModel(User::class, [$id]);
    }

    /** The rule every wallet movement but a deposit uses: a customer of the workspace, or someone with wallet history there. */
    public function isKnownCustomer(Workspace $workspace, string $id): bool
    {
        return $this->customers->isCustomerOf($workspace, $id)
            || WalletEntry::query()->where('workspace_id', $workspace->id)->where('customer_id', $id)->exists();
    }

    /** One entry of the customer's wallet in the workspace. */
    public function entryOf(Workspace $workspace, string $customerId, string $entryId): WalletEntry
    {
        return WalletEntry::query()->where('workspace_id', $workspace->id)->where('customer_id', $customerId)->find($entryId)
            ?? throw (new ModelNotFoundException)->setModel(WalletEntry::class, [$entryId]);
    }

    public function isReversed(WalletEntry $entry): bool
    {
        return $this->reversedAmong($entry->workspace_id, [$entry->id]) !== [];
    }

    /**
     * Numbers of the live documents that took money out of the wallet, newest
     * first: payment vouchers, and receipts that paid an invoice from it.
     *
     * @return list<string>
     */
    public function spentBy(string $workspaceId, string $customerId): array
    {
        $vouchers = PaymentVoucher::query()->where('workspace_id', $workspaceId)->where('customer_id', $customerId)
            ->whereNull('voided_at')->latest()->limit(self::SPENT_BY_LIMIT)->get(['number', 'created_at']);
        $receipts = Receipt::query()->where('workspace_id', $workspaceId)->where('customer_id', $customerId)
            ->where('method', PaymentMethod::Wallet->value)->whereNull('voided_at')->latest()->limit(self::SPENT_BY_LIMIT)->get(['number', 'created_at']);

        return $vouchers->concat($receipts)->sortByDesc('created_at')->take(self::SPENT_BY_LIMIT)
            ->pluck('number')->filter()->map(static fn (mixed $number): string => (string) $number)->values()->all();
    }

    /**
     * @param  array<int, string>  $entryIds
     * @return array<string, true> ids of the given entries that a reversal points at
     */
    private function reversedAmong(string $workspaceId, array $entryIds): array
    {
        if ($entryIds === []) {
            return [];
        }

        return WalletEntry::query()->where('workspace_id', $workspaceId)
            ->where('related_type', WalletLedger::RELATED_ENTRY)
            ->whereIn('related_id', $entryIds)
            ->pluck('related_id')
            ->mapWithKeys(static fn (mixed $id): array => [(string) $id => true])
            ->all();
    }
}
