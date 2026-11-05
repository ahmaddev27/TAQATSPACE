<?php

declare(strict_types=1);

namespace Tests\Feature\Network;

use App\Enums\GapReason;
use App\Enums\RouterStatus;
use App\Enums\WorkspaceFeature;
use App\Models\Router;
use App\Models\RouterGap;
use App\Models\Workspace;
use App\Services\FeatureFlagService;
use App\Services\Network\GapDetector;
use App\Support\Router\RouterKeyHasher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Task 3.6: open a gap when a router goes quiet, close it when it syncs again. */
class GapDetectorTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;

    private Router $router;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-11-06 12:00:00'));
        $this->workspace = Workspace::factory()->create(['timezone' => 'UTC', 'code' => 'ABCD2345']);
        app(FeatureFlagService::class)->update($this->workspace, [WorkspaceFeature::Network->value => true]);
        $this->router = $this->routerSeenMinutesAgo(0, $this->workspace);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** @param array<string, mixed> $attributes */
    private function routerSeenMinutesAgo(int $minutes, Workspace $workspace, array $attributes = []): Router
    {
        return Router::factory()->create(array_merge([
            'workspace_id' => $workspace->id,
            'status' => RouterStatus::Online,
            'last_seen_at' => now()->subMinutes($minutes),
            'boot_id' => 'boot1',
            'clock_offset_seconds' => 0,
        ], $attributes));
    }

    private function gaps(?Router $router = null): \Illuminate\Support\Collection
    {
        return RouterGap::query()->withoutWorkspaceScope()->where('router_id', ($router ?? $this->router)->id)->get();
    }

    private function detector(): GapDetector
    {
        return app(GapDetector::class);
    }

    public function test_a_router_silent_for_twelve_minutes_gets_one_open_gap_and_the_first_sync_closes_it(): void
    {
        $this->router->update(['last_seen_at' => now()->subMinutes(12)]);

        $this->assertSame(1, $this->detector()->detectSilent());

        $gap = $this->gaps()->sole();
        $this->assertSame(GapReason::NoSync, $gap->reason);
        $this->assertNull($gap->ended_at);
        $this->assertNull($gap->resolved_at);
        $this->assertTrue($gap->started_at->equalTo(now()->subMinutes(12)));

        $this->detector()->recordSync($this->router->fresh(), 'boot1', 0, now());

        $closed = $this->gaps()->sole();
        $this->assertTrue($closed->ended_at->equalTo(now()));
        $this->assertNotNull($closed->resolved_at);
        $this->assertSame(GapReason::NoSync, $closed->reason);
    }

    public function test_running_the_detector_repeatedly_does_not_duplicate_gaps(): void
    {
        $this->router->update(['last_seen_at' => now()->subMinutes(12)]);

        $this->detector()->detectSilent();
        Carbon::setTestNow(now()->addMinutes(3));

        $this->assertSame(0, $this->detector()->detectSilent());
        $this->assertCount(1, $this->gaps());
    }

    public function test_a_router_inside_the_threshold_is_not_a_gap(): void
    {
        $this->router->update(['last_seen_at' => now()->subMinutes(4)]);

        $this->assertSame(0, $this->detector()->detectSilent());
        $this->assertCount(0, $this->gaps());
    }

    public function test_routers_that_never_synced_or_are_disabled_are_skipped(): void
    {
        $neverSynced = $this->routerSeenMinutesAgo(0, $this->workspace, ['last_seen_at' => null, 'status' => RouterStatus::Pending]);
        $disabled = $this->routerSeenMinutesAgo(60, $this->workspace, ['status' => RouterStatus::Disabled]);
        $this->router->update(['last_seen_at' => now()->subMinutes(60)]);

        $this->assertSame(1, $this->detector()->detectSilent());
        $this->assertCount(0, $this->gaps($neverSynced));
        $this->assertCount(0, $this->gaps($disabled));
    }

    public function test_a_workspace_without_the_network_module_is_left_alone(): void
    {
        $other = Workspace::factory()->create(['timezone' => 'UTC']);
        $silent = $this->routerSeenMinutesAgo(30, $other);

        $this->assertSame(0, $this->detector()->detectSilent());
        $this->assertCount(0, $this->gaps($silent));

        $this->detector()->recordSync($silent->fresh(), 'rebooted', 0, now());
        $this->assertCount(0, $this->gaps($silent));
    }

    public function test_gaps_are_per_router_and_per_workspace(): void
    {
        $other = Workspace::factory()->create(['timezone' => 'UTC']);
        app(FeatureFlagService::class)->update($other, [WorkspaceFeature::Network->value => true]);
        $silentOther = $this->routerSeenMinutesAgo(30, $other);

        $this->assertSame(1, $this->detector()->detectSilent());

        $this->assertCount(0, $this->gaps());
        $this->assertSame($other->id, $this->gaps($silentOther)->sole()->workspace_id);
    }

    public function test_a_new_boot_id_records_a_reboot_gap_from_the_last_sync(): void
    {
        $this->router->update(['last_seen_at' => now()->subMinutes(2)]);

        $this->detector()->recordSync($this->router->fresh(), 'boot2', 0, now());

        $gap = $this->gaps()->sole();
        $this->assertSame(GapReason::Reboot, $gap->reason);
        $this->assertTrue($gap->started_at->equalTo(now()->subMinutes(2)));
        $this->assertTrue($gap->ended_at->equalTo(now()));
    }

    public function test_a_clock_jump_records_a_clock_jump_gap(): void
    {
        $this->router->update(['last_seen_at' => now()->subMinute()]);

        $this->detector()->recordSync($this->router->fresh(), 'boot1', 3600, now());

        $this->assertSame(GapReason::ClockJump, $this->gaps()->sole()->reason);
    }

    public function test_a_small_clock_drift_and_a_regular_sync_record_nothing(): void
    {
        $this->router->update(['last_seen_at' => now()->subMinute()]);

        $this->detector()->recordSync($this->router->fresh(), 'boot1', 30, now());

        $this->assertCount(0, $this->gaps());
    }

    public function test_a_reboot_during_an_open_gap_closes_it_with_the_more_specific_reason(): void
    {
        $this->router->update(['last_seen_at' => now()->subMinutes(20)]);
        $this->detector()->detectSilent();

        $this->detector()->recordSync($this->router->fresh(), 'boot2', 0, now());

        $gap = $this->gaps()->sole();
        $this->assertSame(GapReason::Reboot, $gap->reason);
        $this->assertNotNull($gap->ended_at);
    }

    public function test_a_sync_after_a_long_silence_the_scheduler_missed_records_a_closed_gap(): void
    {
        $this->router->update(['last_seen_at' => now()->subMinutes(30)]);

        $this->detector()->recordSync($this->router->fresh(), 'boot1', 0, now());
        // The sync handler stamps the router after recording, so the next sync sees a recent last_seen_at.
        $this->router->update(['last_seen_at' => now()]);
        $this->detector()->recordSync($this->router->fresh(), 'boot1', 0, now()->addMinute());

        $this->assertSame(GapReason::NoSync, $this->gaps()->sole()->reason);
    }

    public function test_the_first_sync_of_a_router_is_not_a_gap(): void
    {
        $fresh = $this->routerSeenMinutesAgo(0, $this->workspace, ['last_seen_at' => null, 'boot_id' => null, 'clock_offset_seconds' => null]);

        $this->detector()->recordSync($fresh, 'boot1', 5000, now());

        $this->assertCount(0, $this->gaps($fresh));
    }

    public function test_the_sync_endpoint_closes_the_open_gap(): void
    {
        $key = RouterKeyHasher::generate();
        $this->router->update(['key_hash' => RouterKeyHasher::hash($key), 'last_seen_at' => now()->subMinutes(12)]);
        $this->detector()->detectSilent();

        $this->postJson('/api/v1/hotspot/ABCD2345/sync', [
            'batch_id' => 'gap-1',
            'boot_id' => 'boot1',
            'seq' => 1,
            'now' => now()->getTimestamp(),
            'events' => [],
        ], ['X-API-Key' => $key])->assertOk();

        $gap = $this->gaps()->sole();
        $this->assertNotNull($gap->ended_at);
        $this->assertNotNull($gap->resolved_at);
    }

    public function test_the_detect_command_opens_gaps_and_marks_the_days_for_a_rebuild(): void
    {
        $this->router->update(['last_seen_at' => now()->subMinutes(12)]);

        $this->artisan('routers:detect-gaps')->expectsOutput('Opened 1 gap(s).')->assertSuccessful();

        $this->assertDatabaseHas('attendance_affected_days', ['workspace_id' => $this->workspace->id, 'date' => '2026-11-06']);
    }
}
