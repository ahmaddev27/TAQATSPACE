<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Events\HotspotEventsProcessed;
use App\Models\Device;
use App\Models\HotspotEvent;
use App\Models\HotspotSession;
use App\Models\Router;
use App\Services\Network\AssembledSnapshot;
use App\Services\Network\SessionBuilder;
use App\Services\Network\SnapshotRow;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Raw events, snapshots and devices for session tests, written the way the
 * sync endpoint stores them (times already corrected to the server clock).
 */
trait BuildsSessionFixtures
{
    protected Router $router;

    private int $eventSeq = 0;

    protected function setUpRouter(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-06 12:00:00'));
        $this->router = Router::factory()->create(['boot_id' => 'boot1']);
    }

    protected function device(string $mac, ?string $subscriberId = null): Device
    {
        return Device::query()->withoutWorkspaceScope()->firstOrCreate(
            ['workspace_id' => $this->router->workspace_id, 'mac' => $mac],
            ['subscriber_id' => $subscriberId, 'status' => 'discovered', 'first_seen_at' => now(), 'last_seen_at' => now()],
        );
    }

    /** @param array<string, mixed> $data */
    protected function event(string $type, string $mac, CarbonInterface $at, string $boot = 'boot1', array $data = []): HotspotEvent
    {
        $this->device($mac);

        return HotspotEvent::create([
            'router_id' => $this->router->id,
            'workspace_id' => $this->router->workspace_id,
            'event_uid' => $boot.'-'.++$this->eventSeq,
            'batch_id' => 'batch',
            'type' => $type,
            'mac' => $mac,
            'ip' => '10.0.0.9',
            'username' => 'u1',
            'data' => $data === [] ? null : $data,
            'occurred_at' => $at,
            'received_at' => $at,
        ]);
    }

    /** Feeds events to the builder the way the processor hands them over. */
    protected function feed(HotspotEvent ...$events): void
    {
        HotspotEventsProcessed::dispatch($this->router->id, array_map(fn (HotspotEvent $event): int => $event->id, $events));
    }

    /** @param list<string> $macs */
    protected function snapshot(array $macs, CarbonInterface $takenAt, int $uptime = 60): AssembledSnapshot
    {
        $rows = array_map(fn (string $mac): SnapshotRow => new SnapshotRow($mac, 'u1', '10.0.0.1', $uptime, 100, 200), $macs);

        return new AssembledSnapshot($this->router->id, $this->router->workspace_id, $takenAt, $rows);
    }

    /** @param list<string> $macs */
    protected function applySnapshot(array $macs, CarbonInterface $takenAt, int $uptime = 60): void
    {
        app(SessionBuilder::class)->applySnapshot($this->snapshot($macs, $takenAt, $uptime));
    }

    /** @return list<HotspotSession> */
    protected function sessions(): array
    {
        return HotspotSession::query()->withoutWorkspaceScope()->orderBy('started_at')->orderBy('id')->get()->all();
    }

    protected function at(string $time): Carbon
    {
        return Carbon::parse("2026-11-06 {$time}");
    }
}
