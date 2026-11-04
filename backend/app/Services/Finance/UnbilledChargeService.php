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
use Illuminate\Support\Str;
use InvalidArgumentException;

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
    public function markInvoiced(Workspace $workspace, array $ids, string $invoiceLineId, ?User $actor = null): int
    {
        $ids = array_values(array_unique($ids));

        $updated = DB::transaction(function () use ($workspace, $ids, $invoiceLineId): int {
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

        $this->auditInvoiced($workspace, $ids, $actor);

        return $updated;
    }

    /**
     * Same as markInvoiced for many lines at once, in a single statement.
     * All-or-nothing: every charge must be open.
     *
     * @param  array<string, string>  $lineByCharge  charge id => invoice line id
     */
    public function markInvoicedForLines(Workspace $workspace, array $lineByCharge, ?User $actor = null): int
    {
        if ($lineByCharge === []) {
            return 0;
        }

        $pdo = DB::connection()->getPdo();
        $case = 'CASE id';
        foreach ($lineByCharge as $chargeId => $lineId) {
            $case .= ' WHEN '.$pdo->quote($this->uuid((string) $chargeId)).' THEN '.$pdo->quote($this->uuid($lineId));
        }
        $case .= ' END';

        $updated = UnbilledCharge::query()->withoutWorkspaceScope()
            ->where('workspace_id', $workspace->id)
            ->whereIn('id', array_keys($lineByCharge))
            ->where('status', UnbilledChargeStatus::Open->value)
            ->update(['status' => UnbilledChargeStatus::Invoiced->value, 'invoice_line_id' => DB::raw($case)]);

        if ($updated !== count($lineByCharge)) {
            throw UnbilledChargeException::notOpen();
        }

        $this->auditInvoiced($workspace, array_keys($lineByCharge), $actor);

        return $updated;
    }

    /**
     * Put the charges billed on these lines back to open, e.g. when their invoice is voided.
     *
     * @param  array<int, string>  $lineIds
     */
    public function releaseForLines(Workspace $workspace, array $lineIds): int
    {
        return UnbilledCharge::query()->withoutWorkspaceScope()
            ->where('workspace_id', $workspace->id)
            ->whereIn('invoice_line_id', $lineIds)
            ->where('status', UnbilledChargeStatus::Invoiced->value)
            ->update(['status' => UnbilledChargeStatus::Open->value, 'invoice_line_id' => null]);
    }

    /** @param  array<int, string>  $chargeIds */
    private function auditInvoiced(Workspace $workspace, array $chargeIds, ?User $actor): void
    {
        $this->audit->log(
            'finance.unbilled.invoiced',
            actor: $actor,
            changes: ['charge_ids' => array_values($chargeIds)],
            workspaceId: $workspace->id,
        );
    }

    /** Ids are interpolated into SQL, so they must be well-formed UUIDs. */
    private function uuid(string $value): string
    {
        return Str::isUuid($value) ? $value : throw new InvalidArgumentException('Invalid UUID.');
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
