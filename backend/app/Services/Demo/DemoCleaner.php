<?php

declare(strict_types=1);

namespace App\Services\Demo;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

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

            // Mass deletes: no model events, so the audit log's append-only guard stays untouched.
            $workspaces = Workspace::withTrashed()->whereIn('id', $workspaceIds)->forceDelete();
            $users = User::query()->whereIn('id', $userIds)->delete();

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
}
