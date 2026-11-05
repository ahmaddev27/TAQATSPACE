<?php

declare(strict_types=1);

namespace App\Services\Demo;

use App\Models\HourPackage;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Demo\Steps\HourPackagesStep;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Removes demo data and nothing else. Scope is "owned by a demo account or a
 * demo workspace"; the workspace and user deletes rely on the schema's cascading
 * foreign keys, and the tables without foreign keys are cleared explicitly.
 */
class DemoCleaner
{
    /** Polymorphic or key-less tables that point at a user by id. */
    private const USER_POINTERS = [
        ['notifications', 'notifiable_id'],
        ['personal_access_tokens', 'tokenable_id'],
        ['model_has_roles', 'model_id'],
        ['model_has_permissions', 'model_id'],
    ];

    /** @return array{users: int, workspaces: int} rows removed */
    public function clean(): array
    {
        return DB::transaction(function (): array {
            $userIds = User::query()->where('email', 'like', '%@'.DemoMarker::emailDomain())->pluck('id');
            // By owner, never by slug: a real workspace named "Demo Hub" also gets a demo- slug.
            $workspaceIds = Workspace::withTrashed()->whereIn('owner_id', $userIds)->pluck('id');

            $this->clearKeylessTables($userIds->all(), $workspaceIds->all());

            // import_batches go with the workspace; only their parked files need removing.
            Storage::disk('local')->delete(DB::table('import_batches')->whereIn('workspace_id', $workspaceIds)->whereNotNull('payload_path')->pluck('payload_path')->all());

            // The cascade clears wallets with the workspace; naming them here keeps a schema change from leaving ledgers behind.
            DB::table('payment_vouchers')->whereIn('workspace_id', $workspaceIds)->delete();
            DB::table('wallet_entries')->whereIn('workspace_id', $workspaceIds)->delete();

            // Items restrict deleting their category, so the workspace's cascade would fail on them.
            DB::table('catalog_items')->whereIn('workspace_id', $workspaceIds)->delete();

            // Mass deletes: no model events, so the audit log's append-only guard stays untouched.
            $workspaces = Workspace::withTrashed()->whereIn('id', $workspaceIds)->forceDelete();
            $users = User::query()->whereIn('id', $userIds)->delete();
            $this->clearDemoHourPackages();

            return ['users' => (int) $users, 'workspaces' => (int) $workspaces];
        });
    }

    /**
     * @param  list<string>  $userIds
     * @param  list<string>  $workspaceIds
     */
    private function clearKeylessTables(array $userIds, array $workspaceIds): void
    {
        DB::table('audit_logs')
            ->where(fn ($query) => $query->whereIn('workspace_id', $workspaceIds)->orWhereIn('actor_id', $userIds))
            ->delete();

        foreach (self::USER_POINTERS as [$table, $column]) {
            DB::table($table)->whereIn($column, $userIds)->delete();
        }
    }

    /**
     * Hour packages belong to the platform, not to a workspace; the code prefix marks the demo ones.
     * Run after the users are gone, because a purchase keeps its package in place.
     */
    private function clearDemoHourPackages(): void
    {
        $packages = DB::table('hour_packages')
            ->where('code', 'like', HourPackagesStep::CODE_PREFIX.'%')
            ->whereNotExists(fn ($query) => $query->select(DB::raw(1))
                ->from('hour_package_purchases')
                ->whereColumn('hour_package_purchases.hour_package_id', 'hour_packages.id'))
            ->pluck('id')
            ->all();

        DB::table('audit_logs')->where('subject_type', (new HourPackage)->getMorphClass())->whereIn('subject_id', $packages)->delete();
        DB::table('hour_packages')->whereIn('id', $packages)->delete();
    }
}
