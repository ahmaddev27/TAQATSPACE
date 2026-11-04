<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Enums\SessionEndReason;
use App\Models\Device;
use App\Models\HotspotSession;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Bulk reads and writes for session building. Every method is one statement
 * (or one per chunk), so a build step never queries inside a loop.
 */
class HotspotSessionRepository
{
    private const WRITE_CHUNK = 500;

    private const UPDATE_COLUMNS = ['subscriber_id', 'ended_at', 'last_seen_at', 'last_snapshot_at', 'missed_snapshots', 'end_reason', 'bytes_in', 'bytes_out'];

    /** @return Collection<int, HotspotSession> */
    public function openForRouter(string $routerId): Collection
    {
        return $this->query()->where('router_id', $routerId)->whereNull('ended_at')->get();
    }

    /**
     * All open sessions of the router plus the sessions of `$deviceIds` that
     * ended at or after `$since`, so a replayed or late event finds what it
     * belongs to.
     *
     * @param  list<int>  $deviceIds
     * @return Collection<int, HotspotSession>
     */
    public function openAndRecent(string $routerId, array $deviceIds, CarbonInterface $since): Collection
    {
        return $this->query()
            ->where('router_id', $routerId)
            ->where(fn (Builder $query) => $query
                ->whereNull('ended_at')
                ->orWhere(fn (Builder $recent) => $recent->whereIn('device_id', $deviceIds)->where('ended_at', '>=', $since)))
            ->get();
    }

    /**
     * Latest end per device, so a session opened from a snapshot never
     * overlaps one that already ended.
     *
     * @param  list<int>  $deviceIds
     * @return array<int, CarbonInterface>
     */
    public function latestEnds(string $routerId, array $deviceIds): array
    {
        if ($deviceIds === []) {
            return [];
        }

        return $this->query()
            ->where('router_id', $routerId)
            ->whereIn('device_id', $deviceIds)
            ->whereNotNull('ended_at')
            ->groupBy('device_id')
            ->select('device_id', DB::raw('MAX(ended_at) as latest_end'))
            ->pluck('latest_end', 'device_id')
            ->map(static fn (mixed $value): CarbonInterface => Carbon::parse((string) $value))
            ->all();
    }

    /**
     * @param  list<string>  $macs  normalized
     * @return Collection<string, Device>
     */
    public function devicesByMac(string $workspaceId, array $macs): Collection
    {
        if ($macs === []) {
            return collect();
        }

        return Device::query()
            ->withoutWorkspaceScope()
            ->where('workspace_id', $workspaceId)
            ->whereIn('mac', $macs)
            ->get()
            ->keyBy('mac');
    }

    /** @param list<HotspotSession> $created */
    public function insert(array $created): void
    {
        $rows = array_map(static fn (HotspotSession $session): array => $session->getAttributes(), $created);

        foreach (array_chunk($rows, self::WRITE_CHUNK) as $chunk) {
            HotspotSession::query()->withoutWorkspaceScope()->insert($chunk);
        }
    }

    /** @param list<HotspotSession> $changed */
    public function saveChanges(array $changed): void
    {
        $rows = array_map(static fn (HotspotSession $session): array => $session->getAttributes(), $changed);

        foreach (array_chunk($rows, self::WRITE_CHUNK) as $chunk) {
            HotspotSession::query()->withoutWorkspaceScope()->upsert($chunk, ['id'], self::UPDATE_COLUMNS);
        }
    }

    /** Closes the router's open sessions nobody vouched for since `$cutoff`, at their last contact. */
    public function closeIdle(string $routerId, CarbonInterface $cutoff): int
    {
        return $this->query()
            ->where('router_id', $routerId)
            ->whereNull('ended_at')
            ->where('last_seen_at', '<', $cutoff)
            ->update(['ended_at' => DB::raw('last_seen_at'), 'end_reason' => SessionEndReason::Timeout->value]);
    }

    /** @return Collection<int, string> */
    public function routersWithIdleSessions(CarbonInterface $cutoff): Collection
    {
        return $this->query()->whereNull('ended_at')->where('last_seen_at', '<', $cutoff)->distinct()->pluck('router_id');
    }

    /** @return Builder<HotspotSession> */
    private function query(): Builder
    {
        return HotspotSession::query()->withoutWorkspaceScope();
    }
}
