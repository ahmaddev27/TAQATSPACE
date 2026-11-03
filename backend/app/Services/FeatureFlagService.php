<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\WorkspaceFeature;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceFeatureFlag;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Reads and changes per-workspace feature flags. The full flag map of a
 * workspace is cached as one entry, so a check costs a single cache hit.
 */
class FeatureFlagService
{
    private const CACHE_TTL_SECONDS = 3600;

    public function __construct(private readonly AuditLogger $audit) {}

    public function isEnabled(string $workspaceId, WorkspaceFeature $feature): bool
    {
        return $this->flagsFor($workspaceId)[$feature->value];
    }

    /**
     * @return array<string, bool> Every feature, defaulting to disabled.
     */
    public function flagsFor(string $workspaceId): array
    {
        return Cache::remember(
            $this->cacheKey($workspaceId),
            self::CACHE_TTL_SECONDS,
            fn (): array => $this->flagMap(
                WorkspaceFeatureFlag::query()->where('workspace_id', $workspaceId)->get(),
            ),
        );
    }

    /**
     * Build the full flag map from already-loaded rows (no query), for lists
     * that eager-load the `featureFlags` relation.
     *
     * @param  Collection<int, WorkspaceFeatureFlag>  $rows
     * @return array<string, bool>
     */
    public function flagMap(Collection $rows): array
    {
        $map = array_fill_keys(WorkspaceFeature::values(), false);

        foreach ($rows as $row) {
            $map[$row->feature->value] = $row->enabled;
        }

        return $map;
    }

    /**
     * Apply the given changes, audit the ones that actually flip a flag and
     * drop the cached map.
     *
     * @param  array<string, bool>  $changes  feature value => enabled
     * @return array<string, bool> The resulting full flag map.
     */
    public function update(Workspace $workspace, array $changes, ?User $actor = null): array
    {
        $before = $this->flagMap($workspace->featureFlags()->get());
        $diff = array_filter(
            $changes,
            static fn (bool $enabled, string $feature): bool => $before[$feature] !== $enabled,
            ARRAY_FILTER_USE_BOTH,
        );

        if ($diff === []) {
            return $before;
        }

        DB::transaction(function () use ($workspace, $diff): void {
            foreach ($diff as $feature => $enabled) {
                WorkspaceFeatureFlag::query()->updateOrCreate(
                    ['workspace_id' => $workspace->id, 'feature' => $feature],
                    ['enabled' => $enabled],
                );
            }
        });

        Cache::forget($this->cacheKey($workspace->id));

        $this->audit->log(
            'workspace.features.updated',
            $workspace,
            changes: array_map(
                static fn (bool $enabled): array => ['enabled' => $enabled],
                $diff,
            ),
            actor: $actor,
        );

        return array_merge($before, $diff);
    }

    private function cacheKey(string $workspaceId): string
    {
        return 'workspace_features:'.$workspaceId;
    }
}
