<?php

declare(strict_types=1);

namespace Tests\Feature\Network;

use App\Models\HotspotEvent;
use App\Models\Router;
use App\Models\RouterApiLog;
use App\Models\RouterSnapshot;
use App\Models\RouterSnapshotChunk;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PruneNetworkDataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Cutoff = start of the month containing (now - 180 days) = 2026-01-01.
        Carbon::setTestNow('2026-07-15 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_raw_events_are_deleted_only_once_their_month_is_closed_plus_retention(): void
    {
        $router = Router::factory()->create();
        $old = $this->event($router, 'old', '2025-12-31 23:59:59', now());
        $edge = $this->event($router, 'edge', '2026-01-01 00:00:00', now());
        $recent = $this->event($router, 'recent', '2026-07-01 00:00:00', now());

        $this->artisan('network:prune')->assertSuccessful();

        $this->assertDatabaseMissing('hotspot_events', ['id' => $old->id]);
        $this->assertDatabaseHas('hotspot_events', ['id' => $edge->id]);
        $this->assertDatabaseHas('hotspot_events', ['id' => $recent->id]);
    }

    public function test_unprocessed_raw_events_are_never_deleted(): void
    {
        $router = Router::factory()->create();
        $pending = $this->event($router, 'pending', '2025-01-01 00:00:00', null);

        $this->artisan('network:prune')->assertSuccessful();

        $this->assertDatabaseHas('hotspot_events', ['id' => $pending->id]);
    }

    public function test_snapshot_chunks_follow_the_seven_day_boundary_and_assembly(): void
    {
        $router = Router::factory()->create();
        $assembled = $this->snapshot($router, 'a', now()->subDay());
        $pending = $this->snapshot($router, 'b', null);

        $old = $this->chunk($assembled, 0, now()->subDays(7)->subSecond());
        $edge = $this->chunk($assembled, 1, now()->subDays(7)->addSecond());
        $unassembled = $this->chunk($pending, 0, now()->subDays(30));

        $this->artisan('network:prune')->assertSuccessful();

        $this->assertDatabaseMissing('router_snapshot_chunks', ['id' => $old->id]);
        $this->assertDatabaseHas('router_snapshot_chunks', ['id' => $edge->id]);
        $this->assertDatabaseHas('router_snapshot_chunks', ['id' => $unassembled->id]);
    }

    public function test_router_api_logs_follow_the_fourteen_day_boundary(): void
    {
        $router = Router::factory()->create();
        $old = RouterApiLog::factory()->create(['router_id' => $router->id, 'created_at' => now()->subDays(14)->subSecond()]);
        $edge = RouterApiLog::factory()->create(['router_id' => $router->id, 'created_at' => now()->subDays(14)->addSecond()]);

        $this->artisan('network:prune')->assertSuccessful();

        $this->assertDatabaseMissing('router_api_logs', ['id' => $old->id]);
        $this->assertDatabaseHas('router_api_logs', ['id' => $edge->id]);
    }

    public function test_dry_run_deletes_nothing_and_reports_counts(): void
    {
        $router = Router::factory()->create();
        $this->event($router, 'old', '2025-01-01 00:00:00', now());
        RouterApiLog::factory()->create(['router_id' => $router->id, 'created_at' => now()->subDays(20)]);

        $this->artisan('network:prune', ['--dry-run' => true])
            ->expectsOutputToContain('Would prune 1 row(s) from raw_events.')
            ->expectsOutputToContain('Would prune 1 row(s) from router_api_logs.')
            ->assertSuccessful();

        $this->assertDatabaseCount('hotspot_events', 1);
        $this->assertDatabaseCount('router_api_logs', 1);
    }

    public function test_deletes_in_batches_and_repeated_runs_are_safe(): void
    {
        config(['retention.batch_size' => 2]);
        $router = Router::factory()->create();
        RouterApiLog::factory()->count(5)->create(['router_id' => $router->id, 'created_at' => now()->subDays(20)]);
        $kept = RouterApiLog::factory()->create(['router_id' => $router->id]);

        $this->artisan('network:prune')->expectsOutputToContain('Pruned 5 row(s) from router_api_logs.')->assertSuccessful();
        $this->artisan('network:prune')->expectsOutputToContain('Pruned 0 row(s) from router_api_logs.')->assertSuccessful();

        $this->assertDatabaseCount('router_api_logs', 1);
        $this->assertDatabaseHas('router_api_logs', ['id' => $kept->id]);
    }

    public function test_command_is_scheduled(): void
    {
        $commands = collect(app(Schedule::class)->events())->pluck('command')->implode(' ');

        $this->assertStringContainsString('network:prune', $commands);
    }

    private function event(Router $router, string $uid, string $occurredAt, ?Carbon $processedAt): HotspotEvent
    {
        $event = HotspotEvent::query()->create([
            'router_id' => $router->id,
            'workspace_id' => $router->workspace_id,
            'event_uid' => $uid,
            'batch_id' => 'batch',
            'type' => 'login',
            'occurred_at' => $occurredAt,
            'received_at' => $occurredAt,
        ]);
        $event->forceFill(['processed_at' => $processedAt])->save();

        return $event;
    }

    private function snapshot(Router $router, string $id, ?Carbon $assembledAt): RouterSnapshot
    {
        $snapshot = RouterSnapshot::query()->create([
            'router_id' => $router->id,
            'snapshot_id' => $id,
            'total_chunks' => 2,
            'taken_at' => now(),
        ]);
        $snapshot->forceFill(['assembled_at' => $assembledAt])->save();

        return $snapshot;
    }

    private function chunk(RouterSnapshot $snapshot, int $index, Carbon $createdAt): RouterSnapshotChunk
    {
        $chunk = RouterSnapshotChunk::query()->create(['router_snapshot_id' => $snapshot->id, 'chunk_index' => $index, 'payload' => 'x']);
        $chunk->forceFill(['created_at' => $createdAt])->save();

        return $chunk;
    }
}
