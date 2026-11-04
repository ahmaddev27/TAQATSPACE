<?php

declare(strict_types=1);

namespace Tests\Feature\Network;

use App\Enums\SessionEndReason;
use App\Enums\SessionSource;
use App\Models\HotspotSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsSessionFixtures;
use Tests\TestCase;

class SessionBuilderTest extends TestCase
{
    use BuildsSessionFixtures, RefreshDatabase;

    private const MAC = 'AA:AA:AA:AA:AA:01';

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpRouter();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_login_then_logout_makes_one_session_with_the_right_duration(): void
    {
        $login = $this->event('login', self::MAC, $this->at('10:00:00'));
        $logout = $this->event('logout', self::MAC, $this->at('10:45:30'), data: ['bytes_in' => 500, 'bytes_out' => 900]);

        $this->feed($login, $logout);

        [$session] = $this->sessions();
        $this->assertCount(1, $this->sessions());
        $this->assertSame(2730, $session->durationSeconds());
        $this->assertSame(SessionEndReason::Logout, $session->end_reason);
        $this->assertSame(SessionSource::Event, $session->source);
        $this->assertSame([500, 900], [$session->bytes_in, $session->bytes_out]);
        $this->assertSame($this->router->workspace_id, $session->workspace_id);
    }

    public function test_login_alone_leaves_an_open_session_linked_to_the_subscriber(): void
    {
        $this->device(self::MAC, $subscriber = \App\Models\User::factory()->create()->id);

        $this->feed($this->event('login', self::MAC, $this->at('10:00:00')));

        [$session] = $this->sessions();
        $this->assertTrue($session->isOpen());
        $this->assertSame($subscriber, $session->subscriber_id);
    }

    public function test_reprocessing_the_same_events_changes_nothing(): void
    {
        $events = [
            $this->event('login', self::MAC, $this->at('10:00:00')),
            $this->event('logout', self::MAC, $this->at('10:30:00')),
            $this->event('login', self::MAC, $this->at('11:00:00')),
        ];

        $this->feed(...$events);
        $before = DB::table('hotspot_sessions')->get()->toArray();
        $this->feed(...$events);
        $this->feed(...$events);

        $this->assertEquals($before, DB::table('hotspot_sessions')->get()->toArray());
        $this->assertCount(2, $before);
    }

    public function test_events_arriving_out_of_order_still_pair_up(): void
    {
        $logout = $this->event('logout', self::MAC, $this->at('10:30:00'));
        $login = $this->event('login', self::MAC, $this->at('10:00:00'));

        $this->feed($logout, $login);

        [$session] = $this->sessions();
        $this->assertSame(1800, $session->durationSeconds());
    }

    public function test_a_late_logout_in_a_later_batch_closes_the_session(): void
    {
        $this->feed($this->event('login', self::MAC, $this->at('10:00:00')));
        $this->feed($this->event('logout', self::MAC, $this->at('10:10:00')));

        $this->assertSame(600, $this->sessions()[0]->durationSeconds());
    }

    public function test_a_duplicate_login_while_connected_does_not_open_a_second_session(): void
    {
        $this->feed($this->event('login', self::MAC, $this->at('10:00:00')), $this->event('login', self::MAC, $this->at('10:05:00')));

        $this->assertCount(1, $this->sessions());
        $this->assertEquals($this->at('10:05:00'), $this->sessions()[0]->last_seen_at);
    }

    public function test_logout_without_login_and_without_uptime_invents_nothing(): void
    {
        $this->feed($this->event('logout', self::MAC, $this->at('10:30:00')));

        $this->assertSame([], $this->sessions());
    }

    public function test_logout_without_login_is_recovered_from_its_uptime(): void
    {
        $this->feed($this->event('logout', self::MAC, $this->at('10:30:00'), data: ['uptime' => '20m']));
        $this->feed($this->event('logout', self::MAC, $this->at('10:30:00'), data: ['uptime' => '20m']));

        [$session] = $this->sessions();
        $this->assertCount(1, $this->sessions());
        $this->assertEquals($this->at('10:10:00'), $session->started_at);
        $this->assertSame(1200, $session->durationSeconds());
    }

    public function test_a_second_logout_does_not_stretch_the_session_or_create_a_new_one(): void
    {
        $this->feed($this->event('login', self::MAC, $this->at('10:00:00')), $this->event('logout', self::MAC, $this->at('10:10:00')));
        $this->feed($this->event('logout', self::MAC, $this->at('10:20:00'), data: ['uptime' => '20m']));

        $this->assertCount(1, $this->sessions());
        $this->assertSame(600, $this->sessions()[0]->durationSeconds());
    }

    public function test_events_with_an_invalid_mac_or_other_types_are_ignored(): void
    {
        $this->feed($this->event('login', 'not-a-mac', $this->at('10:00:00')), $this->event('heartbeat', self::MAC, $this->at('10:00:00')));

        $this->assertSame([], $this->sessions());
    }

    public function test_the_same_mac_on_two_routers_gets_separate_sessions(): void
    {
        $this->feed($this->event('login', self::MAC, $this->at('10:00:00')));
        $other = \App\Models\Router::factory()->create(['workspace_id' => $this->router->workspace_id]);
        $this->router = $other;
        $this->feed($this->event('login', self::MAC, $this->at('10:01:00')));

        $this->assertCount(2, $this->sessions());
        $this->assertCount(2, array_unique(array_map(fn (HotspotSession $s): string => $s->router_id, $this->sessions())));
    }

    public function test_a_snapshot_opens_a_session_whose_login_was_lost_dated_by_the_router_uptime(): void
    {
        $this->applySnapshot([self::MAC], $this->at('11:00:00'), uptime: 600);

        [$session] = $this->sessions();
        $this->assertSame(SessionSource::Snapshot, $session->source);
        $this->assertEquals($this->at('10:50:00'), $session->started_at);
        $this->assertTrue($session->isOpen());
        $this->assertSame([100, 200], [$session->bytes_in, $session->bytes_out]);
    }

    public function test_a_snapshot_confirms_an_event_session_instead_of_duplicating_it(): void
    {
        $this->feed($this->event('login', self::MAC, $this->at('10:00:00')));
        $this->applySnapshot([self::MAC], $this->at('10:30:00'), uptime: 1800);
        $this->applySnapshot([self::MAC], $this->at('10:30:00'), uptime: 1800);

        $this->assertCount(1, $this->sessions());
        $this->assertEquals($this->at('10:30:00'), $this->sessions()[0]->last_seen_at);
        $this->assertSame(SessionSource::Event, $this->sessions()[0]->source);
    }

    public function test_a_session_is_closed_after_missing_two_snapshots(): void
    {
        $this->feed($this->event('login', self::MAC, $this->at('10:00:00')));

        $this->applySnapshot([], $this->at('10:05:00'));
        $this->assertTrue($this->sessions()[0]->isOpen());
        $this->assertSame(1, $this->sessions()[0]->missed_snapshots);

        $this->applySnapshot([], $this->at('10:10:00'));

        [$session] = $this->sessions();
        $this->assertSame(SessionEndReason::SnapshotMissing, $session->end_reason);
        $this->assertEquals($this->at('10:00:00'), $session->ended_at);
    }

    public function test_replaying_a_missing_snapshot_does_not_count_twice(): void
    {
        $this->feed($this->event('login', self::MAC, $this->at('10:00:00')));

        $this->applySnapshot([], $this->at('10:05:00'));
        $this->applySnapshot([], $this->at('10:05:00'));

        $this->assertTrue($this->sessions()[0]->isOpen());
        $this->assertSame(1, $this->sessions()[0]->missed_snapshots);
    }

    public function test_reappearing_in_a_snapshot_resets_the_miss_counter(): void
    {
        $this->feed($this->event('login', self::MAC, $this->at('10:00:00')));

        $this->applySnapshot([], $this->at('10:05:00'));
        $this->applySnapshot([self::MAC], $this->at('10:10:00'));
        $this->applySnapshot([], $this->at('10:15:00'));

        $this->assertTrue($this->sessions()[0]->isOpen());
        $this->assertSame(1, $this->sessions()[0]->missed_snapshots);
    }

    public function test_a_login_after_the_snapshot_was_taken_is_not_counted_as_missing(): void
    {
        $this->feed($this->event('login', self::MAC, $this->at('10:06:00')));

        $this->applySnapshot([], $this->at('10:05:00'));
        $this->applySnapshot([], $this->at('10:05:30'));

        $this->assertTrue($this->sessions()[0]->isOpen());
    }

    public function test_a_late_snapshot_cannot_reopen_a_session_that_ended_after_it(): void
    {
        $this->feed($this->event('login', self::MAC, $this->at('10:00:00')), $this->event('logout', self::MAC, $this->at('10:30:00')));

        $this->applySnapshot([self::MAC], $this->at('10:20:00'), uptime: 1200);

        $this->assertCount(1, $this->sessions());
        $this->assertFalse($this->sessions()[0]->isOpen());
    }

    public function test_a_session_opened_from_a_snapshot_never_overlaps_the_previous_one(): void
    {
        $this->feed($this->event('login', self::MAC, $this->at('10:00:00')), $this->event('logout', self::MAC, $this->at('10:30:00')));

        $this->applySnapshot([self::MAC], $this->at('11:00:00'), uptime: 3600);

        [$first, $second] = $this->sessions();
        $this->assertEquals($first->ended_at, $second->started_at);
    }

    public function test_a_late_logout_corrects_a_session_closed_by_inference(): void
    {
        $this->feed($this->event('login', self::MAC, $this->at('10:00:00')));
        $this->applySnapshot([], $this->at('10:05:00'));
        $this->applySnapshot([], $this->at('10:10:00'));

        $this->feed($this->event('logout', self::MAC, $this->at('10:40:00'), data: ['uptime' => '40m']));

        [$session] = $this->sessions();
        $this->assertCount(1, $this->sessions());
        $this->assertSame(SessionEndReason::Logout, $session->end_reason);
        $this->assertSame(2400, $session->durationSeconds());
    }

    public function test_idle_sessions_time_out_at_their_last_contact(): void
    {
        $this->feed($this->event('login', self::MAC, $this->at('11:30:00')), $this->event('login', 'AA:AA:AA:AA:AA:02', $this->at('11:50:00')));

        $closed = app(\App\Services\Network\SessionBuilder::class)->closeIdle($this->router->id);

        $this->assertSame(1, $closed);
        [$old, $recent] = $this->sessions();
        $this->assertSame(SessionEndReason::Timeout, $old->end_reason);
        $this->assertEquals($this->at('11:30:00'), $old->ended_at);
        $this->assertTrue($recent->isOpen());
    }

    public function test_the_timeout_is_configurable(): void
    {
        config(['network.sessions.timeout_minutes' => 60]);
        $this->feed($this->event('login', self::MAC, $this->at('11:30:00')));

        app(\App\Services\Network\SessionBuilder::class)->closeIdle($this->router->id);

        $this->assertTrue($this->sessions()[0]->isOpen());
    }

    public function test_the_global_sweep_closes_idle_sessions_of_every_router(): void
    {
        $this->feed($this->event('login', self::MAC, $this->at('11:00:00')));

        $this->artisan('sessions:close-idle')->assertSuccessful();

        $this->assertSame(SessionEndReason::Timeout, $this->sessions()[0]->end_reason);
    }

    public function test_building_does_not_query_per_event(): void
    {
        $events = [];
        foreach (range(1, 30) as $number) {
            $mac = sprintf('AA:AA:AA:AA:01:%02X', $number);
            $events[] = $this->event('login', $mac, $this->at('10:00:00'));
            $events[] = $this->event('logout', $mac, $this->at('10:30:00'));
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->feed(...$events);
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertCount(30, $this->sessions());
        $this->assertLessThan(15, $queries);
    }
}
