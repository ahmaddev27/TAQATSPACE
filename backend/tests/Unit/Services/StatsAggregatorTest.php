<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Device;
use App\Models\HotspotSession;
use App\Models\StatsDailyWorkspace;
use App\Models\StatsMonthlySubscriber;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Network\AttendanceRebuilder;
use App\Services\Network\StatsAggregator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\BuildsSessionFixtures;
use Tests\TestCase;

class StatsAggregatorTest extends TestCase
{
    use BuildsSessionFixtures, RefreshDatabase;

    private User $first;

    private User $second;

    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpRouter();
        $this->workspace = Workspace::query()->findOrFail($this->router->workspace_id);
        $this->first = User::factory()->create();
        $this->second = User::factory()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function makeSession(User $subscriber, string $start, string $end, int $in = 0, int $out = 0, string $mac = 'AA:AA:AA:AA:AA:01'): void
    {
        $device = Device::query()->withoutWorkspaceScope()->firstOrCreate(
            ['workspace_id' => $this->workspace->id, 'mac' => $mac],
            ['status' => 'discovered', 'first_seen_at' => now(), 'last_seen_at' => now()],
        );

        HotspotSession::query()->withoutWorkspaceScope()->create([
            'router_id' => $this->router->id,
            'workspace_id' => $this->workspace->id,
            'device_id' => $device->id,
            'subscriber_id' => $subscriber->id,
            'started_at' => $start,
            'ended_at' => $end,
            'last_seen_at' => $end,
            'missed_snapshots' => 0,
            'bytes_in' => $in,
            'bytes_out' => $out,
            'source' => 'event',
        ]);
    }

    /** Gaza is UTC+2 on these dates: 10:00Z is 12:00 local. */
    private function seedDay(): void
    {
        $this->makeSession($this->first, '2026-11-05 10:00:00', '2026-11-05 11:00:00', 100, 50);
        $this->makeSession($this->second, '2026-11-05 10:30:00', '2026-11-05 11:30:00', 200, 20, 'AA:AA:AA:AA:AA:02');
        $this->makeSession($this->first, '2026-11-06 10:00:00', '2026-11-06 10:30:00', 10, 5);
    }

    private function aggregate(string $from = '2026-11-05', string $to = '2026-11-06'): array
    {
        app(AttendanceRebuilder::class)->rebuild($this->workspace, $from, $to);

        return app(StatsAggregator::class)->aggregate($this->workspace, $from, $to);
    }

    /** @return list<array<string, mixed>> */
    private function tables(): array
    {
        $strip = fn ($row): array => collect($row->getAttributes())->except('id')->all();

        return [
            StatsDailyWorkspace::query()->withoutWorkspaceScope()->orderBy('date')->get()->map($strip)->all(),
            StatsMonthlySubscriber::query()->withoutWorkspaceScope()->orderBy('subscriber_id')->orderBy('month')->get()->map($strip)->all(),
        ];
    }

    public function test_a_day_is_summed_from_attendance_and_sessions(): void
    {
        $this->seedDay();

        $this->assertSame(['days' => 2, 'subscriber_months' => 2], $this->aggregate());

        $day = StatsDailyWorkspace::query()->withoutWorkspaceScope()->whereDate('date', '2026-11-05')->firstOrFail();
        $this->assertSame(2, $day->dau);
        $this->assertSame(120, $day->active_minutes);
        $this->assertSame(300, $day->bytes_in);
        $this->assertSame(70, $day->bytes_out);
        $this->assertSame(2, $day->peak_concurrent);
        $this->assertSame(12, $day->peak_hour);
        $this->assertCount(24, $day->hourly_presence);
        $this->assertSame([2, 1], [$day->hourly_presence[12], $day->hourly_presence[13]]);
    }

    public function test_a_day_without_attendance_still_gets_a_row(): void
    {
        $this->makeSession($this->first, '2026-11-06 10:00:00', '2026-11-06 10:30:00');

        $this->aggregate('2026-11-04', '2026-11-06');

        $quiet = StatsDailyWorkspace::query()->withoutWorkspaceScope()->whereDate('date', '2026-11-04')->firstOrFail();
        $this->assertSame([0, 0, 0, null], [$quiet->dau, $quiet->active_minutes, $quiet->peak_concurrent, $quiet->peak_hour]);
        $this->assertSame(3, StatsDailyWorkspace::query()->withoutWorkspaceScope()->count());
    }

    public function test_sessions_that_only_touch_in_the_same_instant_do_not_overlap(): void
    {
        $this->makeSession($this->first, '2026-11-05 10:00:00', '2026-11-05 11:00:00');
        $this->makeSession($this->second, '2026-11-05 11:00:00', '2026-11-05 12:00:00', mac: 'AA:AA:AA:AA:AA:02');

        $this->aggregate('2026-11-05', '2026-11-05');

        $this->assertSame(1, StatsDailyWorkspace::query()->withoutWorkspaceScope()->firstOrFail()->peak_concurrent);
    }

    public function test_a_subscriber_month_counts_days_and_traffic(): void
    {
        $this->seedDay();

        $this->aggregate();

        $month = StatsMonthlySubscriber::query()->withoutWorkspaceScope()->where('subscriber_id', $this->first->id)->firstOrFail();
        $this->assertSame('2026-11-01', $month->month->toDateString());
        $this->assertSame(2, $month->attendance_days);
        $this->assertSame(110, $month->bytes_in);
        $this->assertSame(55, $month->bytes_out);
        $this->assertSame(90, $month->active_actual_minutes);
        $this->assertSame(100.0, (float) $month->attendance_rate);
        $this->assertSame('2026-11-05 10:00:00', $month->first_activity_at->format('Y-m-d H:i:s'));
    }

    public function test_attendance_rate_is_relative_to_the_days_the_workspace_had_attendance(): void
    {
        $this->seedDay();

        $this->aggregate();

        $second = StatsMonthlySubscriber::query()->withoutWorkspaceScope()->where('subscriber_id', $this->second->id)->firstOrFail();
        $this->assertSame(1, $second->attendance_days);
        $this->assertSame(50.0, (float) $second->attendance_rate);
    }

    public function test_a_partial_range_still_produces_whole_month_figures(): void
    {
        $this->seedDay();

        app(AttendanceRebuilder::class)->rebuild($this->workspace, '2026-11-05', '2026-11-06');
        app(StatsAggregator::class)->aggregate($this->workspace, '2026-11-06', '2026-11-06');

        $this->assertSame(1, StatsDailyWorkspace::query()->withoutWorkspaceScope()->count());
        // The month is summed from its first day, not from the start of the range.
        $this->assertSame(2, StatsMonthlySubscriber::query()->withoutWorkspaceScope()->where('subscriber_id', $this->first->id)->value('attendance_days'));
    }

    public function test_aggregating_twice_gives_identical_numbers(): void
    {
        $this->seedDay();

        $this->aggregate();
        $first = $this->tables();
        $this->aggregate();

        $this->assertSame($first, $this->tables());
    }

    public function test_subscribers_that_lost_their_attendance_lose_their_month_row(): void
    {
        $this->seedDay();
        $this->aggregate();

        HotspotSession::query()->withoutWorkspaceScope()->where('subscriber_id', $this->second->id)->delete();
        $this->aggregate();

        $this->assertSame(0, StatsMonthlySubscriber::query()->withoutWorkspaceScope()->where('subscriber_id', $this->second->id)->count());
    }

    public function test_active_members_come_from_subscriptions_covering_the_day(): void
    {
        $this->seedDay();
        Subscription::factory()->create(['workspace_id' => $this->workspace->id, 'member_id' => $this->first->id, 'start_date' => '2026-10-01', 'end_date' => null]);
        Subscription::factory()->create(['workspace_id' => $this->workspace->id, 'member_id' => $this->second->id, 'start_date' => '2026-11-06', 'end_date' => '2026-12-06']);

        $this->aggregate();

        $counts = StatsDailyWorkspace::query()->withoutWorkspaceScope()->orderBy('date')->pluck('active_members', 'date')->all();
        $this->assertSame([1, 2], array_values(array_map('intval', $counts)));
    }

    public function test_weekly_figures_are_folded_from_daily_rows_by_iso_week(): void
    {
        // 2026-11-01 is a Sunday, so 11-02 starts the next ISO week.
        $this->makeSession($this->first, '2026-11-01 10:00:00', '2026-11-01 11:00:00', 1);
        $this->makeSession($this->first, '2026-11-02 10:00:00', '2026-11-02 11:00:00', 2);
        $this->makeSession($this->second, '2026-11-03 10:00:00', '2026-11-03 11:00:00', 4, mac: 'AA:AA:AA:AA:AA:02');
        $this->aggregate('2026-11-01', '2026-11-03');

        $weeks = app(StatsAggregator::class)->weekly($this->workspace, '2026-11-01', '2026-11-03');

        $this->assertCount(2, $weeks);
        $this->assertSame(['2026-10-26', 1, 1, 1], [$weeks[0]['week_start'], $weeks[0]['days'], $weeks[0]['dau_total'], $weeks[0]['peak_concurrent']]);
        $this->assertSame(['2026-11-02', 2, 2, 6], [$weeks[1]['week_start'], $weeks[1]['days'], $weeks[1]['dau_total'], $weeks[1]['bytes_in']]);
        $this->assertSame(1.0, $weeks[1]['dau_average']);
    }
}
