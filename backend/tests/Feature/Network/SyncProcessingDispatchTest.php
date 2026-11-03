<?php

declare(strict_types=1);

namespace Tests\Feature\Network;

use App\Jobs\ProcessRouterBatch;
use App\Models\DevicePresence;
use App\Models\HotspotEvent;
use App\Models\Router;
use App\Models\Workspace;
use App\Support\Router\RouterKeyHasher;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SyncProcessingDispatchTest extends TestCase
{
    use RefreshDatabase;

    private string $key;

    protected function setUp(): void
    {
        parent::setUp();

        $workspace = Workspace::factory()->create(['code' => 'ABCD2345']);
        $this->key = RouterKeyHasher::generate();
        Router::factory()->withKey($this->key)->create(['workspace_id' => $workspace->id]);
    }

    private function sync()
    {
        return $this->postJson('/api/v1/hotspot/ABCD2345/sync', [
            'batch_id' => 'batch-1',
            'boot_id' => 'b7',
            'seq' => 1,
            'now' => now()->getTimestamp(),
            'events' => [['seq' => 1, 'type' => 'login', 'at' => now()->getTimestamp(), 'mac' => 'AA:BB:CC:DD:EE:01', 'username' => 'u1']],
        ], ['X-API-Key' => $this->key]);
    }

    public function test_sync_dispatches_processing_on_the_ingest_queue(): void
    {
        Queue::fake();

        $this->sync()->assertOk();

        Queue::assertPushedOn('ingest', ProcessRouterBatch::class);
    }

    public function test_sync_processes_events_into_device_presence(): void
    {
        $this->sync()->assertOk();

        $this->assertSame(1, DevicePresence::query()->withoutWorkspaceScope()->count());
    }

    public function test_a_failing_dispatch_does_not_fail_sync_or_lose_events(): void
    {
        $this->mock(Dispatcher::class)->shouldReceive('dispatch')->andThrow(new \RuntimeException('queue down'));

        $this->sync()->assertOk();

        $this->assertSame(1, HotspotEvent::query()->whereNull('processed_at')->count());
    }
}
