<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Enums\UnbilledChargeStatus;
use App\Exceptions\UnbilledChargeException;
use App\Models\UnbilledCharge;
use App\Models\User;
use App\Models\Workspace;
use App\Repositories\Eloquent\UnbilledChargeRepository;
use App\Services\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * The ledger of charges waiting to be invoiced. `amount` is always computed
 * here from `qty * unit_price`; recording the same `(source, source_ref)` twice
 * returns the first charge instead of creating a second one.
 */
class UnbilledChargeService
{
    public function __construct(
        private readonly UnbilledChargeRepository $repository,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function record(Workspace $workspace, array $data, ?User $actor = null): UnbilledCharge
    {
        $sourceRef = $data['source_ref'] ?? null;

        if ($sourceRef !== null) {
            $existing = $this->repository->findBySourceRef($workspace, (string) $data['source'], (string) $sourceRef);
            if ($existing !== null) {
                return $existing;
            }
        }

        try {
            $charge = UnbilledCharge::query()->create($this->attributes($workspace, $data, $actor));
        } catch (UniqueConstraintViolationException) {
            // A concurrent producer won the race for the same source_ref.
            return $this->repository->findBySourceRef($workspace, (string) $data['source'], (string) $sourceRef);
        }

        $this->audit->log('finance.unbilled.recorded', $charge, actor: $actor, changes: [
            'customer_id' => $charge->customer_id,
            'source' => $data['source'],
            'amount' => $charge->amount,
        ]);

        return $charge->load('customer:id,name');
    }

    public function void(UnbilledCharge $charge, User $actor): UnbilledCharge
    {
        DB::transaction(function () use (&$charge): void {
            $charge = UnbilledCharge::query()->withoutWorkspaceScope()->lockForUpdate()->findOrFail($charge->id);

            if (! $charge->isOpen()) {
                throw UnbilledChargeException::notOpen();
            }

            $charge->forceFill(['status' => UnbilledChargeStatus::Voided])->save();
        });

        $this->audit->log('finance.unbilled.voided', $charge, actor: $actor);

        return $charge->load('customer:id,name');
    }

    /** @return Collection<int, UnbilledCharge> */
    public function listOpenForCustomer(Workspace $workspace, string $customerId): Collection
    {
        return $this->repository->openForCustomer($workspace, $customerId);
    }

    /**
     * Link open charges to the invoice line they were billed on. All-or-nothing:
     * one charge that is missing or no longer open rejects the whole batch.
     *
     * @param  array<int, string>  $ids
     */
    public function markInvoiced(Workspace $workspace, array $ids, string $invoiceLineId): int
    {
        $ids = array_values(array_unique($ids));

        return DB::transaction(function () use ($workspace, $ids, $invoiceLineId): int {
            $updated = UnbilledCharge::query()->withoutWorkspaceScope()
                ->where('workspace_id', $workspace->id)
                ->whereIn('id', $ids)
                ->where('status', UnbilledChargeStatus::Open->value)
                ->update([
                    'status' => UnbilledChargeStatus::Invoiced->value,
                    'invoice_line_id' => $invoiceLineId,
                ]);

            if ($updated !== count($ids)) {
                throw UnbilledChargeException::notOpen();
            }

            return $updated;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(Workspace $workspace, array $data, ?User $actor): array
    {
        $qty = (string) $data['qty'];
        $unitPrice = (string) $data['unit_price'];

        return [
            'workspace_id' => $workspace->id,
            'customer_id' => $data['customer_id'],
            'subscription_id' => $data['subscription_id'] ?? null,
            'catalog_item_id' => $data['catalog_item_id'] ?? null,
            'description' => $data['description'],
            'qty' => $qty,
            'unit_price' => $unitPrice,
            'amount' => bcmul($qty, $unitPrice, 2),
            'source' => $data['source'],
            'source_ref' => $data['source_ref'] ?? null,
            'status' => UnbilledChargeStatus::Open,
            'occurred_at' => $data['occurred_at'] ?? now(),
            'created_by' => $actor?->id,
        ];
    }
}
