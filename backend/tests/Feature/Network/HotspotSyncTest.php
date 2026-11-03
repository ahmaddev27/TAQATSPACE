<?php

declare(strict_types=1);

namespace Tests\Feature\Network;

use App\Enums\RouterStatus;
use App\Models\HotspotEvent;
use App\Models\Router;
use App\Models\RouterApiLog;
use App\Models\RouterSnapshot;
use App\Models\Workspace;
use App\Support\Router\RouterKeyHasher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class HotspotSyncTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;

    private Router $router;

    private string $key;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = Workspace::factory()->create(['code' => 'ABCD2345']);
        $this->key = RouterKeyHasher::generate();
        $this->router = Router::factory()->withKey($this->key)->create(['workspace_id' => $this->workspace->id]);
    }

    /** @param array<string, mixed> $overrides */
    private function batch(array $overrides = []): array
    {
        return array_merge([
            'batch_id' => 'batch-1',
            'boot_id' => 'b7',
            'seq' => 2,
            'now' => now()->getTimestamp(),
            'events' => [
                ['seq' => 1, 'type' => 'login', 'at' => now()->getTimestamp() - 30, 'mac' => 'AA:BB:CC:DD:EE:01', 'ip' => '10.0.0.5', 'username' => 'u1'],
                ['seq' => 2, 'type' => 'logout', 'at' => now()->getTimestamp() - 5, 'mac' => 'AA:BB:CC:DD:EE:01'],
            ],
        ], $overrides);
    }

    /** @param array<string, mixed> $body */
    private function sync(array $body, ?string $key = null, string $code = 'ABCD2345')
    {
        return $this->postJson("/api/v1/hotspot/{$code}/sync", $body, ['X-API-Key' => $key ?? $this->key]);
    }

    public function test_valid_batch_returns_plain_text_and_stores_events(): void
    {
        $response = $this->sync($this->batch());

        $response->assertOk();
        $this->assertStringStartsWith('text/plain', (string) $response->headers->get('Content-Type'));
        $this->assertMatchesRegularExpression('/^# CWS-SYNC [a-z0-9]{16}\n/', $response->getContent());
        $this->assertLessThanOrEqual(4096, strlen($response->getContent()));
        $this->assertSame(2, HotspotEvent::query()->where('router_id', $this->router->id)->count());
        $this->assertDatabaseHas('hotspot_events', ['event_uid' => 'b7-1', 'workspace_id' => $this->workspace->id, 'type' => 'login']);

        $this->router->refresh();
        $this->assertSame(RouterStatus::Online, $this->router->status);
        $this->assertSame(2, $this->router->last_seq);
        $this->assertNotNull($this->router->last_seen_at);
    }

    public function test_resending_the_same_batch_duplicates_nothing_and_returns_the_same_response(): void
    {
        $first = $this->sync($this->batch())->getContent();
        $second = $this->sync($this->batch())->getContent();

        $this->assertSame($first, $second);
        $this->assertSame(2, HotspotEvent::query()->count());
    }

    public function test_overlapping_batch_with_a_new_id_skips_already_stored_events(): void
    {
        $this->sync($this->batch());
        $this->sync($this->batch(['batch_id' => 'batch-2']));

        $this->assertSame(2, HotspotEvent::query()->count());
    }

    public function test_cached_response_expires_after_ten_minutes(): void
    {
        $first = $this->sync($this->batch())->getContent();

        $this->travel(11)->minutes();
        Cache::flush();

        $this->assertNotSame($first, $this->sync($this->batch(['now' => now()->getTimestamp()]))->getContent());
    }

    public function test_another_routers_key_is_forbidden_and_logged(): void
    {
        $other = Workspace::factory()->create(['code' => 'ZZZZ9999']);
        $otherKey = RouterKeyHasher::generate();
        $otherRouter = Router::factory()->withKey($otherKey)->create(['workspace_id' => $other->id]);

        $this->sync($this->batch(), $otherKey)->assertForbidden();

        $this->assertSame(0, HotspotEvent::query()->count());
        $this->assertDatabaseHas('router_api_logs', ['router_id' => $otherRouter->id, 'response_code' => 403]);
    }

    public function test_unknown_or_missing_key_is_unauthorized(): void
    {
        $this->sync($this->batch(), 'nope')->assertUnauthorized();
        $this->postJson('/api/v1/hotspot/ABCD2345/sync', $this->batch())->assertUnauthorized();
        $this->assertSame(0, RouterApiLog::query()->count());
    }

    public function test_unknown_workspace_code_is_forbidden(): void
    {
        $this->sync($this->batch(), code: 'NOPE0000')->assertForbidden();
    }

    public function test_disabled_and_locked_routers_are_rejected(): void
    {
        foreach ([RouterStatus::Disabled, RouterStatus::Locked] as $status) {
            $this->router->update(['status' => $status]);

            $this->sync($this->batch())->assertForbidden();
        }

        $this->assertSame(0, HotspotEvent::query()->count());
    }

    public function test_previous_key_works_during_grace_and_not_after(): void
    {
        $previous = RouterKeyHasher::generate();
        $this->router->update([
            'previous_key_hash' => RouterKeyHasher::hash($previous),
            'previous_key_expires_at' => now()->addMinutes(30),
        ]);

        $this->sync($this->batch(), $previous)->assertOk();

        $this->router->update(['previous_key_expires_at' => now()->subMinute()]);

        $this->sync($this->batch(['batch_id' => 'batch-9']), $previous)->assertUnauthorized();
    }

    public function test_exceeding_twelve_requests_a_minute_returns_429(): void
    {
        foreach (range(1, 12) as $i) {
            $this->sync($this->batch(['batch_id' => "b-{$i}"]))->assertOk();
        }

        $this->sync($this->batch(['batch_id' => 'b-13']))->assertStatus(429);
        $this->assertDatabaseHas('router_api_logs', ['router_id' => $this->router->id, 'response_code' => 429]);
    }

    public function test_rate_limit_is_per_router_not_per_address(): void
    {
        $otherKey = RouterKeyHasher::generate();
        Router::factory()->withKey($otherKey)->create(['workspace_id' => $this->workspace->id]);

        foreach (range(1, 12) as $i) {
            $this->sync($this->batch(['batch_id' => "b-{$i}"]))->assertOk();
        }

        $this->sync($this->batch(['batch_id' => 'x']), $otherKey)->assertOk();
    }

    public function test_event_times_are_corrected_for_a_clock_one_hour_behind(): void
    {
        $this->freezeTime();
        $routerNow = now()->getTimestamp() - 3600;

        $this->sync($this->batch([
            'now' => $routerNow,
            'events' => [['seq' => 1, 'type' => 'login', 'at' => $routerNow - 60]],
        ]))->assertOk();

        $event = HotspotEvent::query()->firstOrFail();
        $this->assertSame(now()->getTimestamp() - 60, $event->occurred_at->getTimestamp());
        $this->assertSame(3600, $this->router->refresh()->clock_offset_seconds);
    }

    public function test_chunked_snapshot_completes_when_all_chunks_arrive_and_ignores_resends(): void
    {
        $chunk = fn (int $index) => ['id' => 'snap-1', 'index' => $index, 'total' => 3, 'taken_at' => now()->getTimestamp(), 'data' => "part-{$index}"];

        $this->sync($this->batch(['batch_id' => 'c0', 'events' => [], 'snapshot' => $chunk(0)]))->assertOk();
        $this->sync($this->batch(['batch_id' => 'c2', 'events' => [], 'snapshot' => $chunk(2)]))->assertOk();
        $this->sync($this->batch(['batch_id' => 'c2b', 'events' => [], 'snapshot' => $chunk(2)]))->assertOk();

        $snapshot = RouterSnapshot::query()->firstOrFail();
        $this->assertSame(2, $snapshot->received_chunks);
        $this->assertFalse($snapshot->isComplete());

        $this->sync($this->batch(['batch_id' => 'c1', 'events' => [], 'snapshot' => $chunk(1)]))->assertOk();

        $snapshot->refresh();
        $this->assertTrue($snapshot->isComplete());
        $this->assertSame(['part-0', 'part-1', 'part-2'], $snapshot->chunks->pluck('payload')->all());
        $this->assertSame(1, RouterSnapshot::query()->count());
    }

    public function test_invalid_payload_is_rejected(): void
    {
        $this->sync(['batch_id' => 'x'])->assertStatus(422)->assertJsonValidationErrors(['boot_id', 'seq', 'now']);
        $this->sync($this->batch(['snapshot' => ['id' => 's', 'index' => 3, 'total' => 3, 'taken_at' => 1, 'data' => 'x']]))
            ->assertStatus(422)->assertJsonValidationErrors(['snapshot.index']);
        $this->assertSame(0, HotspotEvent::query()->count());
    }

    public function test_successful_requests_are_written_to_the_api_log(): void
    {
        $this->sync($this->batch())->assertOk();

        $this->assertDatabaseHas('router_api_logs', [
            'router_id' => $this->router->id,
            'path' => '/api/v1/hotspot/ABCD2345/sync',
            'response_code' => 200,
        ]);
    }

    public function test_one_hundred_consecutive_batches_succeed(): void
    {
        $failures = 0;

        foreach (range(1, 100) as $i) {
            $this->travel(10)->seconds(); // 6 requests/min, under the 12/min limit
            $status = $this->sync($this->batch([
                'batch_id' => "load-{$i}",
                'events' => [['seq' => $i, 'type' => 'login', 'at' => now()->getTimestamp()]],
            ]))->getStatusCode();
            $failures += $status === 200 ? 0 : 1;
        }

        $this->assertLessThanOrEqual(1, $failures);
        $this->assertSame(100, HotspotEvent::query()->count());
    }
}
