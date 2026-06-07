<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ResourceStatus;
use App\Enums\ResourceType;
use App\Models\Resource;
use App\Models\Workspace;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use RuntimeException;

/**
 * Owner-facing management of a workspace's bookable/physical resources: a
 * filterable, paginated list with a by-status counts summary, plus create/edit/
 * delete — all strictly scoped to the owner's own workspace.
 */
class ResourceService
{
    /**
     * Paginated resources for THIS workspace, newest first.
     *
     * @param  array{type?: string|null, status?: string|null, per_page?: int|null}  $filters
     * @return LengthAwarePaginator<int, Resource>
     */
    public function paginateForWorkspace(Workspace $workspace, array $filters): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 200));

        return $this->filteredQuery($workspace, $filters)
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Counts summary for the owner overview: one entry per status (zero when
     * none) plus the grand total. Computed in SQL.
     *
     * @return array{total: int, by_status: array<string, int>}
     */
    public function summaryForWorkspace(Workspace $workspace): array
    {
        $counts = Resource::query()
            ->where('workspace_id', $workspace->id)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $byStatus = [];
        $total = 0;
        foreach (ResourceStatus::cases() as $status) {
            $count = (int) ($counts[$status->value] ?? 0);
            $byStatus[$status->value] = $count;
            $total += $count;
        }

        return [
            'total' => $total,
            'by_status' => $byStatus,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Workspace $workspace, array $data): Resource
    {
        return $workspace->resources()->create($data)->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Workspace $workspace, Resource $resource, array $data): Resource
    {
        $this->assertOwnedBy($workspace, $resource);

        $resource->update($data);

        return $resource->refresh();
    }

    public function delete(Workspace $workspace, Resource $resource): void
    {
        $this->assertOwnedBy($workspace, $resource);

        $resource->delete();
    }

    /**
     * Base query scoped to the workspace with type + status filters.
     *
     * @param  array{type?: string|null, status?: string|null}  $filters
     * @return Builder<Resource>
     */
    private function filteredQuery(Workspace $workspace, array $filters): Builder
    {
        $query = Resource::query()->where('workspace_id', $workspace->id);

        $type = $filters['type'] ?? null;
        if ($type !== null && $type !== 'all' && ResourceType::tryFrom($type) !== null) {
            $query->where('type', $type);
        }

        $status = $filters['status'] ?? null;
        if ($status !== null && $status !== 'all' && ResourceStatus::tryFrom($status) !== null) {
            $query->where('status', $status);
        }

        return $query;
    }

    private function assertOwnedBy(Workspace $workspace, Resource $resource): void
    {
        if ($resource->workspace_id !== $workspace->id) {
            throw new RuntimeException(__('messages.resource_no_access'));
        }
    }
}
