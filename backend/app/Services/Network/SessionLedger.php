<?php

declare(strict_types=1);

namespace App\Services\Network;

use App\Enums\SessionEndReason;
use App\Enums\SessionSource;
use App\Models\Device;
use App\Models\HotspotSession;
use App\Models\Router;
use Carbon\CarbonInterface;

/**
 * The sessions one build step works on, held in memory so the step can
 * decide in PHP and write back in bulk: every change to a loaded session is
 * picked up as dirty, every new session is collected for one insert.
 */
final class SessionLedger
{
    /** @var array<int, list<HotspotSession>> by device id */
    private array $byDevice = [];

    /** @var list<HotspotSession> */
    private array $loaded = [];

    /** @var list<HotspotSession> */
    private array $created = [];

    /** @param iterable<HotspotSession> $sessions */
    public function __construct(iterable $sessions = [])
    {
        foreach ($sessions as $session) {
            $this->loaded[] = $session;
            $this->byDevice[$session->device_id][] = $session;
        }
    }

    public function open(int $deviceId): ?HotspotSession
    {
        foreach ($this->byDevice[$deviceId] ?? [] as $session) {
            if ($session->isOpen()) {
                return $session;
            }
        }

        return null;
    }

    /** The session of the device that started last at or before `$moment`. */
    public function latestAtOrBefore(int $deviceId, CarbonInterface $moment): ?HotspotSession
    {
        $latest = null;

        foreach ($this->byDevice[$deviceId] ?? [] as $session) {
            if ($session->started_at->lessThanOrEqualTo($moment) && ($latest === null || $session->started_at->greaterThan($latest->started_at))) {
                $latest = $session;
            }
        }

        return $latest;
    }

    /** @return list<HotspotSession> */
    public function openSessions(): array
    {
        return array_values(array_filter([...$this->loaded, ...$this->created], static fn (HotspotSession $session): bool => $session->isOpen()));
    }

    public function begin(Router $router, Device $device, CarbonInterface $startedAt, CarbonInterface $lastSeenAt, SessionSource $source): HotspotSession
    {
        $session = new HotspotSession([
            'router_id' => $router->getKey(),
            'workspace_id' => $router->workspace_id,
            'device_id' => $device->getKey(),
            'subscriber_id' => $device->subscriber_id,
            'started_at' => $startedAt,
            'ended_at' => null,
            'last_seen_at' => $lastSeenAt,
            'last_snapshot_at' => null,
            'missed_snapshots' => 0,
            'end_reason' => null,
            'bytes_in' => 0,
            'bytes_out' => 0,
            'source' => $source,
        ]);

        $this->created[] = $session;
        $this->byDevice[$session->device_id][] = $session;

        return $session;
    }

    /** A session never ends before it started, whatever order the evidence arrives in. */
    public function end(HotspotSession $session, CarbonInterface $at, SessionEndReason $reason): void
    {
        $endedAt = $at->lessThan($session->started_at) ? $session->started_at : $at;

        $session->ended_at = $endedAt;
        $session->end_reason = $reason;
        $session->last_seen_at = $endedAt->greaterThan($session->last_seen_at) ? $endedAt : $session->last_seen_at;
    }

    /** Ends the session at the last moment the router vouched for it. */
    public function endAtLastContact(HotspotSession $session, SessionEndReason $reason): void
    {
        $this->end($session, $session->last_seen_at, $reason);
    }

    public function touch(HotspotSession $session, CarbonInterface $at): void
    {
        if ($at->greaterThan($session->last_seen_at)) {
            $session->last_seen_at = $at;
        }
    }

    /** @return list<HotspotSession> */
    public function created(): array
    {
        return $this->created;
    }

    /** @return list<HotspotSession> */
    public function changed(): array
    {
        return array_values(array_filter($this->loaded, static fn (HotspotSession $session): bool => $session->isDirty()));
    }
}
