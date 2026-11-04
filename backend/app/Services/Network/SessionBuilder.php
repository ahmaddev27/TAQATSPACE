<?php

declare(strict_types=1);

namespace App\Services\Network;

use App\Enums\SessionEndReason;
use App\Models\HotspotEvent;
use App\Models\Router;
use App\Repositories\Eloquent\HotspotSessionRepository;
use App\Support\Network\MacAddress;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Builds `hotspot_sessions` from what routers report: login/logout events,
 * snapshots, silence (timeout) and restarts (a new boot id). Each router is
 * built under its own lock, so two workers never decide on the same
 * sessions at once; every step reads the router's sessions once, decides in
 * PHP and writes back in bulk.
 */
class SessionBuilder
{
    public function __construct(
        private readonly HotspotSessionRepository $sessions,
        private readonly EventSessionApplier $eventApplier,
        private readonly SnapshotSessionApplier $snapshotApplier,
        private readonly DeviceResolver $devices,
    ) {}

    /** @param list<int> $eventIds */
    public function applyEvents(string $routerId, array $eventIds): void
    {
        $this->locked($routerId, fn () => $this->buildFromEvents($routerId, $eventIds));
    }

    public function applySnapshot(AssembledSnapshot $snapshot): void
    {
        $this->locked($snapshot->routerId, fn () => $this->buildFromSnapshot($snapshot));
    }

    /**
     * A router that reports a different boot than its sessions were built
     * under has restarted: whatever it kept open is closed at the last
     * moment it vouched for it, never at "now".
     */
    public function reconcileBoot(string $routerId): void
    {
        $this->locked($routerId, function () use ($routerId): void {
            $router = $this->router($routerId);

            if ($router->boot_id === null || $router->boot_id === $router->sessions_boot_id) {
                return;
            }

            $ledger = new SessionLedger($router->sessions_boot_id === null ? [] : $this->sessions->openForRouter($routerId));
            $this->closeAll($ledger);
            $this->persist($router, $ledger, new BootMark($router->boot_id, now()));
        });
    }

    /** Closes the router's sessions that nobody has vouched for within the timeout. */
    public function closeIdle(string $routerId): int
    {
        return $this->locked($routerId, fn (): int => $this->sessions->closeIdle($routerId, $this->idleCutoff()));
    }

    /** @return int routers swept; for routers that stopped syncing and so never run a job */
    public function closeIdleEverywhere(): int
    {
        $routerIds = $this->sessions->routersWithIdleSessions($this->idleCutoff());

        foreach ($routerIds as $routerId) {
            $this->closeIdle($routerId);
        }

        return $routerIds->count();
    }

    /** @param list<int> $eventIds */
    private function buildFromEvents(string $routerId, array $eventIds): void
    {
        $router = $this->router($routerId);
        $events = $this->eventsFor($routerId, $eventIds);

        // Marked in the same transaction as the sessions: a failure leaves the events unbuilt for the retry.
        DB::transaction(function () use ($router, $routerId, $eventIds, $events): void {
            if ($events->isNotEmpty()) {
                $this->buildLedger($router, $events);
            }

            HotspotEvent::query()->where('router_id', $routerId)->whereIn('id', $eventIds)->update(['sessions_built_at' => now()]);
        });
    }

    /** @param Collection<int, HotspotEvent> $events */
    private function buildLedger(Router $router, Collection $events): void
    {
        $devices = $this->sessions->devicesByMac($router->workspace_id, $this->macsOf($events));
        $since = $events->first()->occurred_at->copy()->subMinutes((int) config('network.sessions.late_event_window_minutes'));
        $ledger = new SessionLedger($this->sessions->openAndRecent($router->getKey(), $devices->pluck('id')->all(), $since));

        $this->persist($router, $ledger, $this->eventApplier->apply($router, $ledger, $events, $devices));
    }


    private function buildFromSnapshot(AssembledSnapshot $snapshot): void
    {
        $router = $this->router($snapshot->routerId);
        $devices = $this->devices->resolveSnapshot($router, $snapshot);
        $ledger = new SessionLedger($this->sessions->openForRouter($snapshot->routerId));
        $withoutOpen = $devices->pluck('id')->reject(fn (int $id): bool => $ledger->open($id) !== null)->values()->all();

        $this->snapshotApplier->apply($router, $ledger, $snapshot, $devices, $this->sessions->latestEnds($snapshot->routerId, $withoutOpen));

        $this->persist($router, $ledger, $this->snapshotBoot($router, $snapshot));
    }

    private function snapshotBoot(Router $router, AssembledSnapshot $snapshot): BootMark
    {
        return $router->sessions_boot_id === null
            ? new BootMark($router->boot_id, $snapshot->takenAt)
            : new BootMark($router->sessions_boot_id, $router->sessions_boot_since);
    }

    private function closeAll(SessionLedger $ledger): void
    {
        foreach ($ledger->openSessions() as $session) {
            $ledger->endAtLastContact($session, SessionEndReason::Reboot);
        }
    }

    private function persist(Router $router, SessionLedger $ledger, BootMark $boot): void
    {
        DB::transaction(function () use ($router, $ledger, $boot): void {
            $this->sessions->insert($ledger->created());
            $this->sessions->saveChanges($ledger->changed());

            if ($boot->id !== $router->sessions_boot_id) {
                Router::query()->withoutWorkspaceScope()->whereKey($router->getKey())->update([
                    'sessions_boot_id' => $boot->id,
                    'sessions_boot_since' => $boot->since,
                ]);
            }
        });
    }

    /**
     * @param  list<int>  $eventIds
     * @return Collection<int, HotspotEvent>
     */
    private function eventsFor(string $routerId, array $eventIds): Collection
    {
        return HotspotEvent::query()
            ->where('router_id', $routerId)
            ->whereIn('id', $eventIds)
            ->whereIn('type', ['login', 'logout'])
            ->whereNotNull('mac')
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  Collection<int, HotspotEvent>  $events
     * @return list<string>
     */
    private function macsOf(Collection $events): array
    {
        return $events->map(fn (HotspotEvent $event): ?string => MacAddress::normalize($event->mac))->filter()->unique()->values()->all();
    }

    private function router(string $routerId): Router
    {
        return Router::query()->withoutWorkspaceScope()->findOrFail($routerId);
    }

    private function idleCutoff(): CarbonInterface
    {
        return now()->subMinutes((int) config('network.sessions.timeout_minutes'));
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $work
     * @return T
     */
    private function locked(string $routerId, Closure $work): mixed
    {
        return Cache::lock("router:{$routerId}:sessions", (int) config('network.sessions.lock_seconds'))
            ->block((int) config('network.sessions.lock_wait_seconds'), $work);
    }
}
