<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\User;
use App\Models\WalletEntry;
use App\Models\Workspace;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Wallet reads. Every query names the workspace, so another workspace's
 * entries and customers are never returned.
 */
class WalletRepository
{
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
     * @return LengthAwarePaginator<int, WalletEntry>
     */
    public function paginateEntries(Workspace $workspace, string $customerId, int $perPage): LengthAwarePaginator
    {
        return WalletEntry::query()
            ->with('creator:id,name')
            ->where('workspace_id', $workspace->id)
            ->where('customer_id', $customerId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(max(1, min($perPage, 200)))
            ->withQueryString();
    }

    /** A customer of the workspace, or one whose wallet has history there. */
    public function customer(Workspace $workspace, string $id): User
    {
        $user = User::query()->select('id', 'name')->find($id);
        $known = $user !== null && (
            $this->customers->isCustomerOf($workspace, $id)
            || WalletEntry::query()->where('workspace_id', $workspace->id)->where('customer_id', $id)->exists()
        );

        return $known ? $user : throw (new ModelNotFoundException)->setModel(User::class, [$id]);
    }
}
