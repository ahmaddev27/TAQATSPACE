<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Enums\RouterStatus;
use App\Enums\WorkspaceFeature;
use App\Models\Router;
use App\Models\RouterGap;
use App\Models\WorkspaceFeatureFlag;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/** Reads and writes router gaps in bulk: no query inside a loop. */
class RouterGapRepository
{
    private const MAX_PER_PAGE = 100;

    private const DEFAULT_PER_PAGE = 25;

    /**
     * Gaps that overlap [`$from`, `$to`): they started before it ends and are open or end after it starts.
     *
     * @return Collection<int, RouterGap>
     */
    public function touching(string $workspaceId, CarbonInterface $from, CarbonInterface $to): Collection
    {
        return RouterGap::query()
            ->withoutWorkspaceScope()
            ->where('workspace_id', $workspaceId)
            ->where('started_at', '<', $to)
            ->where(fn (Builder $query) => $query->whereNull('ended_at')->orWhere('ended_at', '>', $from))
            ->get();
    }

    public function openFor(string $routerId): ?RouterGap
    {
        return RouterGap::query()->withoutWorkspaceScope()->where('router_id', $routerId)->open()->first();
    }

    /**
     * Syncing routers of workspaces with the network module that have been silent since before `$cutoff`
     * and have no open gap yet. A router that never synced has nothing to be missing, and a disabled
     * one is silent on purpose.
     *
     * @return Collection<int, Router>
     */
    public function silentWithoutGap(CarbonInterface $cutoff): Collection
    {
        return Router::query()
            ->withoutWorkspaceScope()
            ->whereNotNull('last_seen_at')
            ->where('last_seen_at', '<=', $cutoff)
            ->where('status', '!=', RouterStatus::Disabled->value)
            ->whereIn('workspace_id', WorkspaceFeatureFlag::query()
                ->where('feature', WorkspaceFeature::Network->value)
                ->where('enabled', true)
                ->select('workspace_id'))
            ->whereNotExists(fn ($query) => $query->selectRaw('1')
                ->from('router_gaps')
                ->whereColumn('router_gaps.router_id', 'routers.id')
                ->whereNull('router_gaps.ended_at'))
            ->get(['id', 'workspace_id', 'last_seen_at']);
    }

    /**
     * @param  array<string, mixed>  $filters  router_id, open (bool), from/to (Y-m-d, on started_at), per_page
     */
    public function paginate(string $workspaceId, array $filters): LengthAwarePaginator
    {
        return RouterGap::query()
            ->withoutWorkspaceScope()
            ->where('workspace_id', $workspaceId)
            ->when($filters['router_id'] ?? null, fn (Builder $q, string $v) => $q->where('router_id', $v))
            ->when(isset($filters['open']), fn (Builder $q) => filter_var($filters['open'], FILTER_VALIDATE_BOOLEAN) ? $q->open() : $q->whereNotNull('ended_at'))
            ->when($filters['from'] ?? null, fn (Builder $q, string $v) => $q->where('started_at', '>=', $v.' 00:00:00'))
            ->when($filters['to'] ?? null, fn (Builder $q, string $v) => $q->where('started_at', '<=', $v.' 23:59:59'))
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->paginate(min((int) ($filters['per_page'] ?? self::DEFAULT_PER_PAGE), self::MAX_PER_PAGE));
    }
}
