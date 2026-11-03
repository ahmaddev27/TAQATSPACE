<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Enums\UnbilledChargeStatus;
use App\Models\PosOrder;
use App\Models\Subscription;
use App\Models\UnbilledCharge;
use App\Models\Workspace;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Unbilled-charge lookups. Every query names the workspace explicitly (on top
 * of the model's tenancy scope) so another workspace's charge is never returned.
 */
class UnbilledChargeRepository
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, UnbilledCharge>
     */
    public function paginate(Workspace $workspace, array $filters): LengthAwarePaginator
    {
        $query = UnbilledCharge::query()->with('customer:id,name')->where('workspace_id', $workspace->id);

        foreach (['customer_id', 'source', 'status'] as $column) {
            if (! empty($filters[$column])) {
                $query->where($column, $filters[$column]);
            }
        }

        $perPage = max(1, min((int) ($filters['per_page'] ?? 50), 200));

        return $query->orderByDesc('occurred_at')->orderByDesc('id')->paginate($perPage)->withQueryString();
    }

    /** @return Collection<int, UnbilledCharge> */
    public function openForCustomer(Workspace $workspace, string $customerId): Collection
    {
        return UnbilledCharge::query()
            ->where('workspace_id', $workspace->id)
            ->where('customer_id', $customerId)
            ->where('status', UnbilledChargeStatus::Open->value)
            ->orderBy('occurred_at')
            ->get();
    }

    public function find(Workspace $workspace, string $id): UnbilledCharge
    {
        return UnbilledCharge::query()->with('customer:id,name')->where('workspace_id', $workspace->id)->find($id)
            ?? throw (new ModelNotFoundException)->setModel(UnbilledCharge::class, [$id]);
    }

    public function findBySourceRef(Workspace $workspace, string $source, string $sourceRef): ?UnbilledCharge
    {
        return UnbilledCharge::query()
            ->where('workspace_id', $workspace->id)
            ->where('source', $source)
            ->where('source_ref', $sourceRef)
            ->first();
    }

    /** Whether the user is a subscriber of the workspace or has ordered from its POS. */
    public function isCustomerOf(Workspace $workspace, string $userId): bool
    {
        return Subscription::query()
            ->where('workspace_id', $workspace->id)
            ->where('member_id', $userId)
            ->exists()
            || PosOrder::query()
                ->where('workspace_id', $workspace->id)
                ->where('member_id', $userId)
                ->exists();
    }
}
