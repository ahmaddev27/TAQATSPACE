<?php

declare(strict_types=1);

namespace Tests\Feature\Network;

use App\Events\HotspotEventsProcessed;
use App\Jobs\ProcessRouterBatch;
use App\Models\DevicePresence;
use App\Models\HotspotEvent;
use App\Models\Router;
use App\Models\RouterSnapshot;
use App\Models\RouterSnapshotChunk;
use App\Services\Network\DevicePresenceService;
use App\Services\Network\HotspotEventProcessor;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class HotspotEventProcessorTest extends TestCase
{
    use RefreshDatabase;

    private Router $router;

    private int $uid = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->router = Router::factory()->create();
    }

    private function event(string $type, string $mac, CarbonInterface $at, ?string $user = 'u1'): HotspotEvent
    {
        return HotspotEvent::create([
            'router_id' => $this->router->id,
            'workspace_id' => $this->router->workspace_id,
            'event_uid' => 'b1-'.++$this->uid,
            'batch_id' => 'batch',
            'type' => $type,
            'mac' => $mac,
            'ip' => '10.0.0.9',
            'username' => $user,
            'occurred_at' => $at,
            'received_at' => $at,
        ]);
    }

    private function presenceMacs(): array
    {
        return DevicePresence::query()->withoutWorkspaceScope()->orderBy('mac')->pluck('mac')->all();
    }

    public function test_login_adds_and_logout_removes_a_device(): void
    {
        $this->event('login', 'aa:aa:aa:aa:aa:01', now()->subMinutes(5));
        $this->event('login', 'AA:AA:AA:AA:AA:02', now()->subMinutes(4));
        $this->event('logout', 'AA:AA:AA:AA:AA:02', now()->subMinutes(3));

        $count = app(HotspotEventProcessor::class)->process($this->router->id);

        $this->assertSame(3, $count);
        $this->assertSame(['AA:AA:AA:AA:AA:01'], $this->presenceMacs());
        $this->assertSame(0, HotspotEvent::whereNull('processed_at')->count());
    }

    public function test_reprocessing_is_idempotent(): void
    {
        $this->event('login', 'AA:AA:AA:AA:AA:01', now()->subMinutes(5));
        $processor = app(HotspotEventProcessor::class);

        $processor->process($this->router->id);
        HotspotEvent::query()->update(['processed_at' => null]);
        $processor->process($this->router->id);

        $this->assertSame(['AA:AA:AA:AA:AA:01'], $this->presenceMacs());
        $this->assertSame(1, DevicePresence::query()->withoutWorkspaceScope()->count());
    }

    public function test_a_late_login_does_not_override_a_newer_logout_or_state(): void
    {
        $this->event('logout', 'AA:AA:AA:AA:AA:01', now()->subMinutes(1));
        $this->event('login', 'AA:AA:AA:AA:AA:01', now()->subMinutes(5));

        app(HotspotEventProcessor::class)->process($this->router->id);

        $this->assertSame([], $this->presenceMacs());
    }

    public function test_events_without_a_mac_or_of_other_types_are_only_marked_processed(): void
    {
        $this->event('other', 'AA:AA:AA:AA:AA:01', now());
        HotspotEvent::create([
            'router_id' => $this->router->id, 'workspace_id' => $this->router->workspace_id, 'event_uid' => 'x', 'batch_id' => 'b',
            'type' => 'login', 'occurred_at' => now(), 'received_at' => now(),
        ]);

        app(HotspotEventProcessor::class)->process($this->router->id);

        $this->assertSame([], $this->presenceMacs());
        $this->assertSame(0, HotspotEvent::whereNull('processed_at')->count());
    }

    public function test_large_backlog_is_processed_in_batches_without_n_plus_one(): void
    {
        $rows = [];
        for ($i = 1; $i <= 1200; $i++) {
            $rows[] = [
                'router_id' => $this->router->id, 'workspace_id' => $this->router->workspace_id, 'event_uid' => 'big-'.$i,
                'batch_id' => 'b', 'type' => 'login', 'mac' => sprintf('AA:AA:AA:AA:%02X:%02X', intdiv($i, 256), $i % 256),
                'occurred_at' => now()->subMinute(), 'received_at' => now(),
            ];
        }
        foreach (array_chunk($rows, 300) as $chunk) {
            HotspotEvent::query()->insert($chunk);
        }

        DB::enableQueryLog();
        $count = app(HotspotEventProcessor::class)->process($this->router->id);
        $queries = count(DB::getQueryLog());

        $this->assertSame(1200, $count);
        $this->assertSame(1200, DevicePresence::query()->withoutWorkspaceScope()->count());
        // 3 batches x (select, existing, upsert chunk(s), update, tx) plus the final empty select.
        $this->assertLessThan(40, $queries);
    }

    public function test_failure_rolls_back_and_keeps_events_unprocessed(): void
    {
        $this->event('login', 'AA:AA:AA:AA:AA:01', now());
        $this->mock(DevicePresenceService::class)->shouldReceive('applyEvents')->andThrow(new \RuntimeException('boom'));

        try {
            app(HotspotEventProcessor::class)->process($this->router->id);
            $this->fail('Expected the failure to propagate.');
        } catch (\RuntimeException) {
            $this->assertSame(1, HotspotEvent::whereNull('processed_at')->count());
        }
    }

    public function test_processed_batches_are_handed_off_to_session_building(): void
    {
        Event::fake([HotspotEventsProcessed::class]);
        $event = $this->event('login', 'AA:AA:AA:AA:AA:01', now());

        app(HotspotEventProcessor::class)->process($this->router->id);

        Event::assertDispatched(HotspotEventsProcessed::class, fn ($e) => $e->eventIds === [$event->id]);
    }

    public function test_job_assembles_snapshot_and_reconciles_with_events(): void
    {
        $this->event('login', 'AA:AA:AA:AA:AA:09', now()->subMinutes(2));
        $snapshot = RouterSnapshot::create([
            'router_id' => $this->router->id, 'snapshot_id' => 's1', 'total_chunks' => 2, 'received_chunks' => 2,
            'taken_at' => now()->subMinute(), 'completed_at' => now(),
        ]);
        RouterSnapshotChunk::create(['router_snapshot_id' => $snapshot->id, 'chunk_index' => 1, 'payload' => 'u3|AA:AA:AA:AA:AA:03|10.0.0.3|3s|3|3']);
        RouterSnapshotChunk::create(['router_snapshot_id' => $snapshot->id, 'chunk_index' => 0, 'payload' => 'u1|AA:AA:AA:AA:AA:01|10.0.0.1|1s|1|1']);

        ProcessRouterBatch::dispatchSync($this->router->id);
        ProcessRouterBatch::dispatchSync($this->router->id);

        $this->assertSame(['AA:AA:AA:AA:AA:01', 'AA:AA:AA:AA:AA:03'], $this->presenceMacs());
        $this->assertNotNull($snapshot->fresh()->assembled_at);
    }

    public function test_older_pending_snapshots_do_not_roll_state_back(): void
    {
        foreach ([['old', 'AA:AA:AA:AA:AA:01', 10], ['new', 'AA:AA:AA:AA:AA:02', 1]] as [$id, $mac, $minutesAgo]) {
            $snapshot = RouterSnapshot::create([
                'router_id' => $this->router->id, 'snapshot_id' => $id, 'total_chunks' => 1, 'received_chunks' => 1,
                'taken_at' => now()->subMinutes($minutesAgo), 'completed_at' => now(),
            ]);
            RouterSnapshotChunk::create(['router_snapshot_id' => $snapshot->id, 'chunk_index' => 0, 'payload' => "u|{$mac}|10.0.0.1|1s|1|1"]);
        }

        ProcessRouterBatch::dispatchSync($this->router->id);

        $this->assertSame(['AA:AA:AA:AA:AA:02'], $this->presenceMacs());
        $this->assertSame(0, RouterSnapshot::whereNull('assembled_at')->count());
    }

    public function test_fallback_command_queues_routers_with_pending_work(): void
    {
        Queue::fake();
        Router::factory()->create();
        $this->event('login', 'AA:AA:AA:AA:AA:01', now());

        $this->artisan('router-data:process-pending')->assertSuccessful();

        Queue::assertPushedOn(ProcessRouterBatch::QUEUE, ProcessRouterBatch::class, fn ($job) => $job->routerId === $this->router->id);
        Queue::assertPushed(ProcessRouterBatch::class, 1);
    }
}
