<?php

declare(strict_types=1);

namespace Tests\Feature\Network;

use App\Models\AttendanceAffectedDay;
use App\Models\AttendanceDaily;
use App\Models\Device;
use App\Models\HotspotSession;
use App\Models\Router;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Network\AttendanceRebuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\BuildsSessionFixtures;
use Tests\TestCase;

class AttendanceRebuilderTest extends TestCase
{
    use BuildsSessionFixtures, RefreshDatabase;

    private const MAC = 'AA:AA:AA:AA:AA:01';

    private User $subscriber;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpRouter();
        $this->subscriber = User::factory()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function workspace(): Workspace
    {
        return Workspace::query()->findOrFail($this->router->workspace_id);
    }

    private function makeSession(string $mac, string $start, ?string $end, ?string $lastSeen = null, ?Router $router = null, ?User $subscriber = null): HotspotSession
    {
        $router ??= $this->router;
        $device = Device::query()->withoutWorkspaceScope()->firstOrCreate(
            ['workspace_id' => $router->workspace_id, 'mac' => $mac],
            ['status' => 'discovered', 'first_seen_at' => now(), 'last_seen_at' => now()],
        );

        return HotspotSession::query()->withoutWorkspaceScope()->create([
            'router_id' => $router->id,
            'workspace_id' => $router->workspace_id,
            'device_id' => $device->id,
            'subscriber_id' => ($subscriber ?? $this->subscriber)->id,
            'started_at' => $start,
            'ended_at' => $end,
            'last_seen_at' => $lastSeen ?? $end ?? $start,
            'missed_snapshots' => 0,
            'bytes_in' => 0,
            'bytes_out' => 0,
            'source' => 'event',
        ]);
    }

    /** @return list<array<string, mixed>> rows without their surrogate key, for comparisons */
    private function rows(): array
    {
        return AttendanceDaily::query()->withoutWorkspaceScope()->orderBy('workspace_id')->orderBy('subscriber_id')->orderBy('date')->get()
            ->map(fn (AttendanceDaily $row): array => collect($row->getAttributes())->except('id')->all())->all();
    }

    public function test_two_devices_of_one_subscriber_are_united_not_summed(): void
    {
        // Gaza is UTC+2 on 2026-11-06, so 10:00Z is 12:00 local.
        $this->makeSession('AA:AA:AA:AA:AA:01', '2026-11-06 10:00:00', '2026-11-06 11:00:00');
        $this->makeSession('AA:AA:AA:AA:AA:02', '2026-11-06 10:30:00', '2026-11-06 11:30:00');

        $this->assertSame(1, app(AttendanceRebuilder::class)->rebuild($this->workspace(), '2026-11-06', '2026-11-06'));

        $row = AttendanceDaily::query()->withoutWorkspaceScope()->firstOrFail();
        $this->assertSame(90, $row->active_actual_minutes);
        $this->assertSame(90, $row->day_span_minutes);
        $this->assertSame(3, $row->half_hour_slots);
        $this->assertSame(2, $row->hour_bucket_hours);
        $this->assertSame(0b111 << 24, $row->slots_bitmap);
        $this->assertSame('2026-11-06', $row->date->toDateString());
        $this->assertFalse($row->has_gap);
    }

    public function test_a_session_across_local_midnight_makes_two_days_in_the_workspace_zone(): void
    {
        $this->makeSession(self::MAC, '2026-11-06 20:40:00', '2026-11-06 23:10:00');

        app(AttendanceRebuilder::class)->rebuild($this->workspace(), '2026-11-06', '2026-11-07');

        $rows = AttendanceDaily::query()->withoutWorkspaceScope()->orderBy('date')->get();
        $this->assertSame(['2026-11-06', '2026-11-07'], $rows->map(fn ($row) => $row->date->toDateString())->all());
        $this->assertSame([80, 70], $rows->pluck('active_actual_minutes')->all());
        $this->assertSame([0b111 << 45, 0b111], $rows->pluck('slots_bitmap')->all());
    }

    public function test_the_workspace_zone_decides_the_day(): void
    {
        $this->workspace()->update(['timezone' => 'UTC']);
        $this->makeSession(self::MAC, '2026-11-06 20:40:00', '2026-11-06 23:10:00');

        app(AttendanceRebuilder::class)->rebuild($this->workspace(), '2026-11-06', '2026-11-07');

        $this->assertSame([150], AttendanceDaily::query()->withoutWorkspaceScope()->pluck('active_actual_minutes')->all());
    }

    public function test_rebuilding_twice_gives_identical_rows(): void
    {
        $this->makeSession(self::MAC, '2026-11-06 07:00:00', '2026-11-06 08:00:00');
        $this->makeSession('AA:AA:AA:AA:AA:02', '2026-11-06 20:40:00', '2026-11-06 23:10:00');

        app(AttendanceRebuilder::class)->rebuild($this->workspace(), '2026-11-05', '2026-11-08');
        $first = $this->rows();
        app(AttendanceRebuilder::class)->rebuild($this->workspace(), '2026-11-05', '2026-11-08');

        $this->assertNotEmpty($first);
        $this->assertSame($first, $this->rows());
    }

    public function test_an_open_session_counts_up_to_the_last_moment_it_was_vouched_for(): void
    {
        $this->makeSession(self::MAC, '2026-11-06 08:00:00', null, '2026-11-06 09:00:00');

        app(AttendanceRebuilder::class)->rebuild($this->workspace(), '2026-11-06', '2026-11-06');

        $this->assertSame(60, AttendanceDaily::query()->withoutWorkspaceScope()->firstOrFail()->active_actual_minutes);
    }

    public function test_an_open_session_never_counts_beyond_now(): void
    {
        $this->makeSession(self::MAC, '2026-11-06 11:00:00', null, '2026-11-06 15:00:00');

        app(AttendanceRebuilder::class)->rebuild($this->workspace(), '2026-11-06', '2026-11-06');

        $this->assertSame(60, AttendanceDaily::query()->withoutWorkspaceScope()->firstOrFail()->active_actual_minutes);
    }

    public function test_sessions_without_a_subscriber_and_other_workspaces_are_left_out(): void
    {
        $other = Router::factory()->create();
        $this->makeSession(self::MAC, '2026-11-06 08:00:00', '2026-11-06 09:00:00', router: $other);
        HotspotSession::query()->withoutWorkspaceScope()->where('workspace_id', $other->workspace_id)->update(['subscriber_id' => null]);
        $this->makeSession('AA:AA:AA:AA:AA:09', '2026-11-06 08:00:00', '2026-11-06 09:00:00', router: $other, subscriber: User::factory()->create());

        $written = app(AttendanceRebuilder::class)->rebuild($this->workspace(), '2026-11-06', '2026-11-06');

        $this->assertSame(0, $written);
        $this->assertSame(0, AttendanceDaily::query()->withoutWorkspaceScope()->count());
    }

    public function test_only_the_requested_range_is_rewritten(): void
    {
        $this->makeSession(self::MAC, '2026-11-04 08:00:00', '2026-11-04 09:00:00');
        $this->makeSession('AA:AA:AA:AA:AA:02', '2026-11-06 08:00:00', '2026-11-06 09:00:00');
        $rebuilder = app(AttendanceRebuilder::class);
        $rebuilder->rebuild($this->workspace(), '2026-11-04', '2026-11-06');
        AttendanceDaily::query()->withoutWorkspaceScope()->where('date', '2026-11-04')->update(['active_actual_minutes' => 999]);

        $rebuilder->rebuild($this->workspace(), '2026-11-06', '2026-11-06');

        $this->assertSame(
            ['2026-11-04' => 999, '2026-11-06' => 60],
            AttendanceDaily::query()->withoutWorkspaceScope()->orderBy('date')->get()->mapWithKeys(fn ($row) => [$row->date->toDateString() => $row->active_actual_minutes])->all(),
        );
    }

    public function test_a_session_from_the_previous_day_still_feeds_the_first_day_of_the_range(): void
    {
        $this->makeSession(self::MAC, '2026-11-05 21:30:00', '2026-11-05 23:30:00');

        app(AttendanceRebuilder::class)->rebuild($this->workspace(), '2026-11-06', '2026-11-06');

        $row = AttendanceDaily::query()->withoutWorkspaceScope()->firstOrFail();
        $this->assertSame('2026-11-06', $row->date->toDateString());
        $this->assertSame(90, $row->active_actual_minutes);
    }

    public function test_the_spring_forward_day_is_stored_with_its_real_23_hours(): void
    {
        $this->makeSession(self::MAC, '2026-03-27 22:00:00', '2026-03-28 21:00:00');

        app(AttendanceRebuilder::class)->rebuild($this->workspace(), '2026-03-28', '2026-03-28');

        $row = AttendanceDaily::query()->withoutWorkspaceScope()->firstOrFail();
        $this->assertSame([1380, 46, 23], [$row->active_actual_minutes, $row->half_hour_slots, $row->hour_bucket_hours]);
    }

    public function test_the_fall_back_day_is_stored_with_its_real_25_hours_in_48_slots(): void
    {
        $this->makeSession(self::MAC, '2026-10-23 21:00:00', '2026-10-24 22:00:00');

        app(AttendanceRebuilder::class)->rebuild($this->workspace(), '2026-10-24', '2026-10-24');

        $row = AttendanceDaily::query()->withoutWorkspaceScope()->firstOrFail();
        $this->assertSame([1500, 48, 24], [$row->active_actual_minutes, $row->half_hour_slots, $row->hour_bucket_hours]);
        $this->assertSame((1 << 48) - 1, $row->slots_bitmap);
    }

    public function test_the_highest_slot_round_trips_through_the_database(): void
    {
        $this->makeSession(self::MAC, '2026-11-06 21:30:00', '2026-11-06 22:00:00');

        app(AttendanceRebuilder::class)->rebuild($this->workspace(), '2026-11-06', '2026-11-06');

        $this->assertSame(1 << 47, AttendanceDaily::query()->withoutWorkspaceScope()->firstOrFail()->slots_bitmap);
    }

    public function test_session_changes_mark_the_days_they_touch(): void
    {
        $this->device(self::MAC, $this->subscriber->id);

        $this->feed($this->event('login', self::MAC, $this->at('21:30:00')), $this->event('logout', self::MAC, $this->at('23:00:00')));

        // 21:30Z-23:00Z on 2026-11-06 is 23:30 local on the 6th until 01:00 local on the 7th.
        $this->assertSame(
            ['2026-11-06', '2026-11-07'],
            AttendanceAffectedDay::query()->orderBy('date')->get()->map(fn ($mark) => $mark->date->toDateString())->all(),
        );
    }

    public function test_a_late_event_that_changes_a_past_day_is_picked_up_by_the_affected_run(): void
    {
        $this->device(self::MAC, $this->subscriber->id);
        $this->feed($this->event('login', self::MAC, $this->at('10:00:00')));
        $this->applySnapshot([], $this->at('10:05:00'));
        $this->applySnapshot([], $this->at('10:10:00'));
        $rebuilder = app(AttendanceRebuilder::class);

        // A mark from the same second as a run is kept (it may have landed after the run read its list).
        Carbon::setTestNow('2026-11-06 12:00:05');
        $rebuilder->rebuildAffected();
        $this->assertSame(0, AttendanceAffectedDay::query()->count());
        $this->assertSame(0, AttendanceDaily::query()->withoutWorkspaceScope()->firstOrFail()->active_actual_minutes);

        Carbon::setTestNow('2026-11-08 09:00:00');
        $this->feed($this->event('logout', self::MAC, $this->at('10:40:00'), data: ['uptime' => '40m']));
        $rebuilder->rebuildAffected();

        $this->assertSame(40, AttendanceDaily::query()->withoutWorkspaceScope()->firstOrFail()->active_actual_minutes);
        $this->assertSame(1, AttendanceDaily::query()->withoutWorkspaceScope()->count());
    }

    public function test_the_affected_run_rebuilds_only_the_marked_days(): void
    {
        $this->makeSession(self::MAC, '2026-11-04 08:00:00', '2026-11-04 09:00:00');
        $this->makeSession('AA:AA:AA:AA:AA:02', '2026-11-06 08:00:00', '2026-11-06 09:00:00');
        app(AttendanceRebuilder::class)->rebuild($this->workspace(), '2026-11-04', '2026-11-06');
        AttendanceDaily::query()->withoutWorkspaceScope()->update(['active_actual_minutes' => 999]);
        AttendanceAffectedDay::query()->create(['workspace_id' => $this->router->workspace_id, 'date' => '2026-11-06', 'marked_at' => '2026-11-06 10:00:00']);

        app(AttendanceRebuilder::class)->rebuildAffected();

        $minutes = AttendanceDaily::query()->withoutWorkspaceScope()->orderBy('date')->pluck('active_actual_minutes')->all();
        $this->assertSame([999, 60], $minutes);
        $this->assertSame(0, AttendanceAffectedDay::query()->count());
    }

    public function test_a_mark_made_after_the_run_started_survives_it(): void
    {
        AttendanceAffectedDay::query()->create(['workspace_id' => $this->router->workspace_id, 'date' => '2026-11-06', 'marked_at' => '2026-11-06 12:00:00']);
        Carbon::setTestNow('2026-11-06 11:59:59');

        app(AttendanceRebuilder::class)->rebuildAffected();

        $this->assertSame(1, AttendanceAffectedDay::query()->count());
    }

    public function test_the_command_rebuilds_an_explicit_range_and_rejects_bad_dates(): void
    {
        $this->makeSession(self::MAC, '2026-11-06 08:00:00', '2026-11-06 09:00:00');
        $id = $this->router->workspace_id;

        $this->artisan('attendance:rebuild', ['--workspace' => $id, '--from' => '2026-11-06', '--to' => '2026-11-06'])->assertSuccessful();
        $this->assertSame(1, AttendanceDaily::query()->withoutWorkspaceScope()->count());

        $this->artisan('attendance:rebuild', ['--workspace' => $id, '--from' => 'nope', '--to' => '2026-11-06'])->assertExitCode(2);
        $this->artisan('attendance:rebuild', ['--workspace' => $id, '--from' => '2026-11-07', '--to' => '2026-11-06'])->assertExitCode(2);
    }

    public function test_the_nightly_pass_rebuilds_the_last_two_local_days(): void
    {
        $this->makeSession(self::MAC, '2026-11-05 08:00:00', '2026-11-05 09:00:00');
        $this->makeSession('AA:AA:AA:AA:AA:02', '2026-11-03 08:00:00', '2026-11-03 09:00:00');

        $this->artisan('attendance:rebuild', ['--recent' => true])->assertSuccessful();

        $this->assertSame(['2026-11-05'], AttendanceDaily::query()->withoutWorkspaceScope()->get()->map(fn ($row) => $row->date->toDateString())->all());
    }
}
