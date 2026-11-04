<?php

declare(strict_types=1);

namespace App\Services\Network;

use App\Models\DevicePresence;
use App\Models\HotspotEvent;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Keeps `device_presence` ("connected now") in step with what a router
 * reports. Every write is guarded by `last_seen_at`, so a late snapshot or an
 * out-of-order event can never overwrite newer state, and every write is
 * bulk (one read, one upsert, one delete per call).
 */
class DevicePresenceService
{
    private const WRITE_CHUNK = 500;

    private const UPSERT_UPDATE_COLUMNS = ['hotspot_username', 'ip', 'uptime_seconds', 'bytes_in', 'bytes_out', 'last_seen_at'];

    /** Upserts the snapshot's devices and removes the ones it no longer lists. */
    public function applySnapshot(AssembledSnapshot $snapshot): void
    {
        $existing = $this->existingFor($snapshot->routerId);
        $reported = collect($snapshot->rows)->keyBy(fn (SnapshotRow $row): string => $row->mac);

        $upserts = $reported
            ->reject(fn (SnapshotRow $row): bool => $this->isNewerThan($existing->get($row->mac), $snapshot->takenAt))
            ->map(fn (SnapshotRow $row): array => $this->presenceRow($snapshot, $row))
            ->values();

        $staleIds = $existing
            ->reject(fn (DevicePresence $device): bool => $reported->has($device->mac) || $this->isNewerThan($device, $snapshot->takenAt))
            ->pluck('id');

        $this->upsert($upserts);
        $this->deleteIds($staleIds);
    }

    /**
     * Applies login/logout events of ONE router. Only the latest event per MAC
     * matters, and only if it is not older than what the table already knows.
     *
     * @param  Collection<int, HotspotEvent>  $events
     */
    public function applyEvents(string $routerId, Collection $events): void
    {
        $latest = $events
            ->filter(fn (HotspotEvent $event): bool => $event->mac !== null && in_array($event->type, ['login', 'logout'], true))
            ->sortBy(fn (HotspotEvent $event): array => [$event->occurred_at->getTimestamp(), $event->id])
            ->keyBy(fn (HotspotEvent $event): string => strtoupper((string) $event->mac));

        if ($latest->isEmpty()) {
            return;
        }

        $existing = $this->existingFor($routerId, $latest->keys()->all());

        $current = $latest->reject(fn (HotspotEvent $event, string $mac): bool => $this->isNewerThan($existing->get($mac), $event->occurred_at));

        $this->upsert($current->filter(fn (HotspotEvent $event): bool => $event->type === 'login')
            ->map(fn (HotspotEvent $event, string $mac): array => $this->loginRow($event, $mac, $existing->get($mac)))
            ->values());

        $this->deleteIds($current->filter(fn (HotspotEvent $event): bool => $event->type === 'logout')
            ->map(fn (HotspotEvent $event, string $mac) => $existing->get($mac)?->id)
            ->filter()
            ->values());
    }

    /**
     * @param  list<string>|null  $macs
     * @return Collection<string, DevicePresence>
     */
    private function existingFor(string $routerId, ?array $macs = null): Collection
    {
        return DevicePresence::query()
            ->withoutWorkspaceScope()
            ->where('router_id', $routerId)
            ->when($macs !== null, fn ($query) => $query->whereIn('mac', $macs))
            ->get()
            ->keyBy('mac');
    }

    private function isNewerThan(?DevicePresence $device, CarbonInterface $moment): bool
    {
        return $device !== null && $device->last_seen_at->greaterThan($moment);
    }

    /**
     * @return array<string, mixed>
     */
    private function presenceRow(AssembledSnapshot $snapshot, SnapshotRow $row): array
    {
        return [
            'router_id' => $snapshot->routerId,
            'workspace_id' => $snapshot->workspaceId,
            'mac' => $row->mac,
            'hotspot_username' => $row->username,
            'ip' => $row->ip,
            'uptime_seconds' => $row->uptimeSeconds,
            'bytes_in' => $row->bytesIn,
            'bytes_out' => $row->bytesOut,
            'first_seen_at' => $snapshot->takenAt,
            'last_seen_at' => $snapshot->takenAt,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function loginRow(HotspotEvent $event, string $mac, ?DevicePresence $existing): array
    {
        return [
            'router_id' => $event->router_id,
            'workspace_id' => $event->workspace_id,
            'mac' => $mac,
            'hotspot_username' => $event->username,
            'ip' => $event->ip,
            'uptime_seconds' => 0,
            'bytes_in' => 0,
            'bytes_out' => 0,
            'first_seen_at' => $existing?->first_seen_at ?? $event->occurred_at,
            'last_seen_at' => $event->occurred_at,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     */
    private function upsert(Collection $rows): void
    {
        foreach ($rows->chunk(self::WRITE_CHUNK) as $chunk) {
            DevicePresence::query()->withoutWorkspaceScope()->upsert($chunk->all(), ['router_id', 'mac'], self::UPSERT_UPDATE_COLUMNS);
        }
    }

    /**
     * @param  Collection<int, int>  $ids
     */
    private function deleteIds(Collection $ids): void
    {
        foreach ($ids->chunk(self::WRITE_CHUNK) as $chunk) {
            DevicePresence::query()->withoutWorkspaceScope()->whereIn('id', $chunk->all())->delete();
        }
    }
}
