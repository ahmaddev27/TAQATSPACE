<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Branch;
use App\Models\Seat;
use App\Models\Workspace;
use App\Services\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Collection;

/**
 * Branches of a workspace. The default branch is created with the workspace and
 * can be renamed but never deleted; any other branch can only be deleted once
 * no station is left in it.
 */
class BranchService
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    /** @return Collection<int, Branch> */
    public function list(Workspace $workspace): Collection
    {
        return Branch::query()
            ->withoutWorkspaceScope()
            ->where('workspace_id', $workspace->id)
            ->withCount('seats')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();
    }

    public function defaultBranchId(Workspace $workspace): ?string
    {
        return Branch::query()
            ->withoutWorkspaceScope()
            ->where('workspace_id', $workspace->id)
            ->where('is_default', true)
            ->value('id');
    }

    /** @param array{name: string, address?: string|null} $data */
    public function create(Workspace $workspace, array $data): Branch
    {
        $branch = Branch::create([
            'workspace_id' => $workspace->id,
            'name' => $data['name'],
            'address' => $data['address'] ?? null,
            'is_default' => false,
        ]);

        $this->audit->log('branch.created', $branch, changes: ['name' => $branch->name]);

        return $branch;
    }

    /** @param array{name?: string, address?: string|null} $data */
    public function update(Branch $branch, array $data): Branch
    {
        $branch->fill(array_intersect_key($data, array_flip(['name', 'address'])))->save();

        $this->audit->log('branch.updated', $branch, changes: $branch->getChanges());

        return $branch->refresh();
    }

    public function delete(Branch $branch): void
    {
        if ($branch->is_default) {
            abort(409, __('messages.branch_default_not_deletable'));
        }

        if (Seat::query()->where('branch_id', $branch->id)->exists()) {
            abort(409, __('messages.branch_has_seats'));
        }

        $branch->delete();

        $this->audit->log('branch.deleted', $branch, changes: ['name' => $branch->name]);
    }
}
