<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\PaymentVoucher;
use App\Models\Workspace;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Payment-voucher lookups. Every query names the workspace, so another
 * workspace's voucher is never returned.
 */
class PaymentVoucherRepository
{
    private const WITH = ['customer:id,name'];

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, PaymentVoucher>
     */
    public function paginate(Workspace $workspace, array $filters): LengthAwarePaginator
    {
        $query = PaymentVoucher::query()->with(self::WITH)->where('workspace_id', $workspace->id);

        if (! empty($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }

        match ($filters['state'] ?? null) {
            'voided' => $query->whereNotNull('voided_at'),
            'active' => $query->whereNull('voided_at'),
            default => null,
        };

        if (! empty($filters['from'])) {
            $query->where('paid_at', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->where('paid_at', '<=', $filters['to'].' 23:59:59');
        }

        $perPage = max(1, min((int) ($filters['per_page'] ?? 50), 200));

        return $query->orderByDesc('paid_at')->orderBy('id')->paginate($perPage)->withQueryString();
    }

    public function find(Workspace $workspace, string $id): PaymentVoucher
    {
        return PaymentVoucher::query()->with(self::WITH)->where('workspace_id', $workspace->id)->find($id)
            ?? throw (new ModelNotFoundException)->setModel(PaymentVoucher::class, [$id]);
    }
}
