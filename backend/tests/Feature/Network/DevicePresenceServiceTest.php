<?php

declare(strict_types=1);

namespace Tests\Feature\Network;

use App\Models\DevicePresence;
use App\Models\Router;
use App\Services\Network\AssembledSnapshot;
use App\Services\Network\DevicePresenceService;
use App\Services\Network\SnapshotRow;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DevicePresenceServiceTest extends TestCase
{
    use RefreshDatabase;

    private Router $router;

    protected function setUp(): void
    {
        parent::setUp();

        $this->router = Router::factory()->create();
    }

    private function snapshot(array $macs, ?CarbonInterface $takenAt = null): AssembledSnapshot
    {
        $rows = array_map(fn (string $mac) => new SnapshotRow($mac, 'user-'.$mac, '10.0.0.1', 60, 100, 200), $macs);

        return new AssembledSnapshot($this->router->id, $this->router->workspace_id, $takenAt ?? now(), $rows);
    }

    private function macs(): array
    {
        return DevicePresence::query()->withoutWorkspaceScope()->orderBy('mac')->pluck('mac')->all();
    }

    public function test_snapshot_upserts_devices(): void
    {
        $service = app(DevicePresenceService::class);

        $service->applySnapshot($this->snapshot(['AA:AA:AA:AA:AA:01', 'AA:AA:AA:AA:AA:02']));
        $service->applySnapshot($this->snapshot(['AA:AA:AA:AA:AA:01', 'AA:AA:AA:AA:AA:02']));

        $this->assertSame(['AA:AA:AA:AA:AA:01', 'AA:AA:AA:AA:AA:02'], $this->macs());
        $device = DevicePresence::query()->withoutWorkspaceScope()->where('mac', 'AA:AA:AA:AA:AA:01')->first();
        $this->assertSame(60, $device->uptime_seconds);
        $this->assertSame($this->router->workspace_id, $device->workspace_id);
    }

    public function test_device_missing_from_next_snapshot_disappears(): void
    {
        $service = app(DevicePresenceService::class);

        $service->applySnapshot($this->snapshot(['AA:AA:AA:AA:AA:01', 'AA:AA:AA:AA:AA:02'], now()->subMinute()));
        $service->applySnapshot($this->snapshot(['AA:AA:AA:AA:AA:02'], now()));

        $this->assertSame(['AA:AA:AA:AA:AA:02'], $this->macs());
    }

    public function test_other_routers_are_untouched(): void
    {
        $other = Router::factory()->create();
        DevicePresence::query()->withoutWorkspaceScope()->create([
            'router_id' => $other->id, 'workspace_id' => $other->workspace_id, 'mac' => 'BB:BB:BB:BB:BB:01',
            'first_seen_at' => now()->subHour(), 'last_seen_at' => now()->subHour(),
        ]);

        app(DevicePresenceService::class)->applySnapshot($this->snapshot([]));

        $this->assertSame(['BB:BB:BB:BB:BB:01'], $this->macs());
    }

    public function test_device_seen_after_the_snapshot_survives_it(): void
    {
        $service = app(DevicePresenceService::class);
        $service->applySnapshot($this->snapshot(['AA:AA:AA:AA:AA:01'], now()));

        $service->applySnapshot($this->snapshot([], now()->subMinute()));

        $this->assertSame(['AA:AA:AA:AA:AA:01'], $this->macs());
    }

    public function test_large_snapshot_uses_a_constant_number_of_queries(): void
    {
        $macs = array_map(fn (int $i) => sprintf('AA:AA:AA:AA:%02X:%02X', intdiv($i, 256), $i % 256), range(1, 1200));
        $service = app(DevicePresenceService::class);
        $service->applySnapshot($this->snapshot($macs, now()->subMinute()));

        DB::enableQueryLog();
        $service->applySnapshot($this->snapshot(array_slice($macs, 0, 600), now()));
        $queries = count(DB::getQueryLog());

        $this->assertLessThanOrEqual(6, $queries);
        $this->assertCount(600, $this->macs());
    }
}
