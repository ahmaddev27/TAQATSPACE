<?php

declare(strict_types=1);

namespace Tests\Feature\Network;

use App\Enums\GapReason;
use App\Models\AttendanceDaily;
use App\Models\Device;
use App\Models\HotspotSession;
use App\Models\Router;
use App\Models\RouterGap;
use App\Models\StatsDailyWorkspace;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Network\AttendanceRebuilder;
use App\Services\Network\StatsAggregator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Task 3.6: days that overlap a gap are flagged, so missing data is not read as absence. */
class GapAttendanceTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;

    private Router $router;

    private User $subscriber;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-11-06 12:00:00'));
        $this->workspace = Workspace::factory()->create(['timezone' => 'UTC']);
        $this->router = Router::factory()->create(['workspace_id' => $this->workspace->id]);
        $this->subscriber = User::factory()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private int $devices = 0;

    private function makeSession(string $start, string $end): void
    {
        $device = Device::query()->withoutWorkspaceScope()->create([
            'workspace_id' => $this->workspace->id,
            // Unique per workspace by index; random MACs collided often enough to fail CI.
            'mac' => sprintf('AA:AA:AA:AA:%02X:%02X', intdiv(++$this->devices, 256), $this->devices % 256),
            'status' => 'discovered',
            'first_seen_at' => $start,
            'last_seen_at' => $end,
        ]);

        HotspotSession::query()->withoutWorkspaceScope()->create([
            'router_id' => $this->router->id,
            'workspace_id' => $this->workspace->id,
            'device_id' => $device->id,
            'subscriber_id' => $this->subscriber->id,
            'started_at' => $start,
            'ended_at' => $end,
            'last_seen_at' => $end,
            'missed_snapshots' => 0,
            'bytes_in' => 0,
            'bytes_out' => 0,
            'source' => 'event',
        ]);
    }

    private function gap(string $start, ?string $end): RouterGap
    {
        return RouterGap::query()->withoutWorkspaceScope()->create([
            'router_id' => $this->router->id,
            'workspace_id' => $this->workspace->id,
            'started_at' => $start,
            'ended_at' => $end,
            'reason' => GapReason::NoSync,
            'resolved_at' => $end,
        ]);
    }

    /** @return array<string, bool> */
    private function flags(): array
    {
        return AttendanceDaily::query()->withoutWorkspaceScope()->orderBy('date')->get()
            ->mapWithKeys(fn (AttendanceDaily $row): array => [$row->date->toDateString() => $row->has_gap])->all();
    }

    public function test_only_the_days_overlapping_a_gap_get_has_gap(): void
    {
        $this->makeSession('2026-11-04 09:00:00', '2026-11-04 10:00:00');
        $this->makeSession('2026-11-05 09:00:00', '2026-11-05 10:00:00');
        $this->makeSession('2026-11-06 09:00:00', '2026-11-06 10:00:00');
        $this->gap('2026-11-05 23:30:00', '2026-11-06 00:20:00');

        app(AttendanceRebuilder::class)->rebuild($this->workspace, '2026-11-04', '2026-11-06');

        $this->assertSame(['2026-11-04' => false, '2026-11-05' => true, '2026-11-06' => true], $this->flags());
    }

    public function test_an_open_gap_flags_the_days_up_to_now(): void
    {
        $this->makeSession('2026-11-05 09:00:00', '2026-11-05 10:00:00');
        $this->makeSession('2026-11-06 09:00:00', '2026-11-06 10:00:00');
        $this->gap('2026-11-06 11:00:00', null);

        app(AttendanceRebuilder::class)->rebuild($this->workspace, '2026-11-05', '2026-11-06');

        $this->assertSame(['2026-11-05' => false, '2026-11-06' => true], $this->flags());
    }

    public function test_a_gap_that_ends_exactly_at_midnight_does_not_flag_the_next_day(): void
    {
        $this->makeSession('2026-11-06 09:00:00', '2026-11-06 10:00:00');
        $this->gap('2026-11-05 22:00:00', '2026-11-06 00:00:00');

        app(AttendanceRebuilder::class)->rebuild($this->workspace, '2026-11-06', '2026-11-06');

        $this->assertSame(['2026-11-06' => false], $this->flags());
    }

    public function test_rebuilding_after_the_gap_is_gone_clears_the_flag(): void
    {
        $this->makeSession('2026-11-05 09:00:00', '2026-11-05 10:00:00');
        $gap = $this->gap('2026-11-05 12:00:00', '2026-11-05 12:30:00');
        $rebuilder = app(AttendanceRebuilder::class);

        $rebuilder->rebuild($this->workspace, '2026-11-05', '2026-11-05');
        $gap->delete();
        $rebuilder->rebuild($this->workspace, '2026-11-05', '2026-11-05');

        $this->assertSame(['2026-11-05' => false], $this->flags());
    }

    public function test_the_daily_statistics_flag_a_gap_day_even_without_any_attendance(): void
    {
        $this->makeSession('2026-11-05 09:00:00', '2026-11-05 10:00:00');
        $this->gap('2026-11-04 22:00:00', '2026-11-05 00:30:00');
        $this->gap('2026-11-05 00:10:00', '2026-11-05 00:45:00');

        app(AttendanceRebuilder::class)->rebuild($this->workspace, '2026-11-03', '2026-11-05');
        app(StatsAggregator::class)->aggregate($this->workspace, '2026-11-03', '2026-11-05');

        $days = StatsDailyWorkspace::query()->withoutWorkspaceScope()->orderBy('date')->get()->keyBy(fn ($day): string => $day->date->toDateString());

        $this->assertFalse($days['2026-11-03']->has_gap);
        $this->assertSame(0, $days['2026-11-03']->gap_minutes);
        $this->assertTrue($days['2026-11-04']->has_gap);
        $this->assertSame(120, $days['2026-11-04']->gap_minutes);
        // The two gaps overlap, so their united span 00:00-00:45 is counted once.
        $this->assertTrue($days['2026-11-05']->has_gap);
        $this->assertSame(45, $days['2026-11-05']->gap_minutes);
        $this->assertSame(1, $days['2026-11-05']->dau);
        $this->assertSame(0, $days['2026-11-04']->dau);
    }

    public function test_a_gap_changes_no_existing_figure(): void
    {
        $this->makeSession('2026-11-05 09:00:00', '2026-11-05 10:00:00');
        app(AttendanceRebuilder::class)->rebuild($this->workspace, '2026-11-05', '2026-11-05');
        app(StatsAggregator::class)->aggregate($this->workspace, '2026-11-05', '2026-11-05');
        $before = StatsDailyWorkspace::query()->withoutWorkspaceScope()->first()->only(['dau', 'active_minutes', 'hour_bucket_hours', 'peak_concurrent']);

        $this->gap('2026-11-05 12:00:00', '2026-11-05 13:00:00');
        app(AttendanceRebuilder::class)->rebuild($this->workspace, '2026-11-05', '2026-11-05');
        app(StatsAggregator::class)->aggregate($this->workspace, '2026-11-05', '2026-11-05');

        $this->assertSame($before, StatsDailyWorkspace::query()->withoutWorkspaceScope()->first()->only(['dau', 'active_minutes', 'hour_bucket_hours', 'peak_concurrent']));
    }
}
