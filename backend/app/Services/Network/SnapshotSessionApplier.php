<?php

declare(strict_types=1);

namespace App\Services\Network;

use App\Enums\SessionEndReason;
use App\Enums\SessionSource;
use App\Models\Device;
use App\Models\HotspotSession;
use App\Models\Router;
use App\Support\Network\MacAddress;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Reconciles open sessions with a snapshot, the authoritative "who is here":
 * a device it lists keeps (or gets) a session; a device it omits is only
 * closed after missing several applied snapshots in a row, because one
 * snapshot can be taken just before a login arrives.
 */
class SnapshotSessionApplier
{
    /**
     * @param  Collection<string, Device>  $devices  keyed by normalized MAC
     * @param  array<int, CarbonInterface>  $latestEnds  latest session end per device id
     */
    public function apply(Router $router, SessionLedger $ledger, AssembledSnapshot $snapshot, Collection $devices, array $latestEnds): void
    {
        $present = $this->presentRows($snapshot, $devices);
        $byId = $devices->keyBy(static fn (Device $device): int => $device->getKey());

        foreach ($ledger->openSessions() as $session) {
            isset($present[$session->device_id])
                ? $this->confirm($ledger, $session, $present[$session->device_id], $snapshot->takenAt)
                : $this->miss($ledger, $session, $snapshot->takenAt);
        }

        foreach ($present as $deviceId => $row) {
            if ($ledger->open($deviceId) === null) {
                $this->openFromSnapshot($router, $ledger, $byId->get($deviceId), $row, $snapshot->takenAt, $latestEnds[$deviceId] ?? null);
            }
        }
    }

    /**
     * @param  Collection<string, Device>  $devices
     * @return array<int, SnapshotRow> by device id
     */
    private function presentRows(AssembledSnapshot $snapshot, Collection $devices): array
    {
        $present = [];

        foreach ($snapshot->rows as $row) {
            $device = $devices->get((string) MacAddress::normalize($row->mac));

            if ($device !== null) {
                $present[$device->getKey()] = $row;
            }
        }

        return $present;
    }

    private function confirm(SessionLedger $ledger, HotspotSession $session, SnapshotRow $row, CarbonInterface $takenAt): void
    {
        if ($session->last_snapshot_at !== null && $session->last_snapshot_at->greaterThanOrEqualTo($takenAt)) {
            return;
        }

        // Counters of an older snapshot must not roll back newer ones.
        if ($takenAt->greaterThanOrEqualTo($session->last_seen_at)) {
            $session->bytes_in = $row->bytesIn;
            $session->bytes_out = $row->bytesOut;
        }

        $session->missed_snapshots = 0;
        $session->last_snapshot_at = $takenAt;
        $ledger->touch($session, $takenAt);
    }

    private function miss(SessionLedger $ledger, HotspotSession $session, CarbonInterface $takenAt): void
    {
        $seenAfterSnapshot = $session->started_at->greaterThanOrEqualTo($takenAt) || $session->last_seen_at->greaterThanOrEqualTo($takenAt);
        $alreadyCounted = $session->last_snapshot_at !== null && $session->last_snapshot_at->greaterThanOrEqualTo($takenAt);

        if ($seenAfterSnapshot || $alreadyCounted) {
            return;
        }

        $session->missed_snapshots++;
        $session->last_snapshot_at = $takenAt;

        if ($session->missed_snapshots >= (int) config('network.sessions.snapshot_miss_limit')) {
            $ledger->endAtLastContact($session, SessionEndReason::SnapshotMissing);
        }
    }

    /**
     * The logins were missed, so the router's own uptime dates the session;
     * it is clamped so it never overlaps one that already ended, and nothing
     * is opened for a moment a session already ended after.
     */
    private function openFromSnapshot(Router $router, SessionLedger $ledger, Device $device, SnapshotRow $row, CarbonInterface $takenAt, ?CarbonInterface $latestEnd): void
    {
        if ($latestEnd !== null && $latestEnd->greaterThanOrEqualTo($takenAt)) {
            return;
        }

        $startedAt = $takenAt->copy()->subSeconds($row->uptimeSeconds);
        $startedAt = $latestEnd !== null && $startedAt->lessThan($latestEnd) ? $latestEnd->copy() : $startedAt;

        $session = $ledger->begin($router, $device, $startedAt, $takenAt, SessionSource::Snapshot);
        $session->last_snapshot_at = $takenAt;
        $session->bytes_in = $row->bytesIn;
        $session->bytes_out = $row->bytesOut;
    }
}
