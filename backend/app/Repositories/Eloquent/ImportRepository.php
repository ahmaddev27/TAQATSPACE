<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\CatalogCategory;
use App\Models\CatalogItem;
use App\Models\ImportBatch;
use App\Models\PosOrder;
use App\Models\Subscription;
use App\Models\UnbilledCharge;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Lookups the importers and the import service need. Every query names the
 * workspace explicitly, in one statement per set of keys (never per row).
 */
class ImportRepository
{
    public function findBatch(Workspace $workspace, string $id, bool $lock = false): ImportBatch
    {
        $query = ImportBatch::query()->withoutWorkspaceScope()->where('workspace_id', $workspace->id);

        return ($lock ? $query->lockForUpdate() : $query)->find($id)
            ?? throw (new ModelNotFoundException)->setModel(ImportBatch::class, [$id]);
    }

    public function findByHash(Workspace $workspace, string $hash): ?ImportBatch
    {
        return ImportBatch::query()->withoutWorkspaceScope()
            ->where('workspace_id', $workspace->id)
            ->where('file_hash', $hash)
            ->first();
    }

    /**
     * Item codes that already exist (archived ones included), lower-cased.
     *
     * @param  list<string>  $codes
     * @return array<string, true>
     */
    public function existingItemCodes(Workspace $workspace, array $codes): array
    {
        $found = CatalogItem::query()->withoutWorkspaceScope()
            ->where('workspace_id', $workspace->id)
            ->whereIn('code', $codes)
            ->pluck('code');

        return array_fill_keys($found->map(static fn (string $code): string => strtolower($code))->all(), true);
    }

    /**
     * Active category ids by lower-cased code.
     *
     * @param  list<string>  $codes
     * @return array<string, string>
     */
    public function activeCategoryIds(Workspace $workspace, array $codes): array
    {
        $found = CatalogCategory::query()->withoutWorkspaceScope()
            ->where('workspace_id', $workspace->id)
            ->whereNull('archived_at')
            ->whereIn('code', $codes)
            ->pluck('id', 'code');

        $ids = [];
        foreach ($found as $code => $id) {
            $ids[strtolower((string) $code)] = (string) $id;
        }

        return $ids;
    }

    /**
     * Imported `source_ref`s that already exist.
     *
     * @param  list<string>  $refs
     * @return array<string, true>
     */
    public function existingImportRefs(Workspace $workspace, array $refs): array
    {
        $found = UnbilledCharge::query()->withoutWorkspaceScope()
            ->where('workspace_id', $workspace->id)
            ->where('source', 'import')
            ->whereIn('source_ref', $refs)
            ->pluck('source_ref');

        return array_fill_keys($found->map(static fn (string $ref): string => strtolower($ref))->all(), true);
    }

    /**
     * Customers of the workspace (subscribers, or members who ordered from its
     * POS) by lower-cased email.
     *
     * @param  list<string>  $emails
     * @return array<string, string> email => user id
     */
    public function customerIdsByEmail(Workspace $workspace, array $emails): array
    {
        $users = User::query()->whereIn('email', $emails)->pluck('id', 'email');
        if ($users->isEmpty()) {
            return [];
        }

        $ids = $users->values()->all();
        $customers = Subscription::query()->where('workspace_id', $workspace->id)->whereIn('member_id', $ids)->pluck('member_id')
            ->merge(PosOrder::query()->where('workspace_id', $workspace->id)->whereIn('member_id', $ids)->pluck('member_id'))
            ->flip();

        $byEmail = [];
        foreach ($users as $email => $id) {
            if ($customers->has($id)) {
                $byEmail[strtolower((string) $email)] = (string) $id;
            }
        }

        return $byEmail;
    }
}
