<?php

declare(strict_types=1);

namespace Tests\Feature\Network;

use App\Enums\SessionEndReason;
use App\Jobs\ProcessRouterBatch;
use App\Models\HotspotSession;
use App\Models\Router;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Network\HotspotEventProcessor;
use App\Services\Network\RouterSnapshotProcessor;
use App\Services\Network\SessionBuilder;
use App\Services\Router\RouterService;
use App\Testing\Hotspot\FakeRouterTransport;
use App\Testing\Hotspot\SimulatedRouterTarget;
use App\Testing\Hotspot\SimulationClock;
use App\Testing\Hotspot\SimulationPlan;
use App\Testing\Hotspot\SimulationRunner;
use App\Testing\Hotspot\SimulationScenario;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\BuildsSessionFixtures;
use Tests\TestCase;

class SessionPipelineTest extends TestCase
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

    public function test_the_event_processor_hands_its_batch_to_session_building(): void
    {
        $this->event('login', self::MAC, $this->at('10:00:00'));
        $this->event('logout', self::MAC, $this->at('10:20:00'));

        app(HotspotEventProcessor::class)->process($this->router->id);

        $this->assertSame(1200, $this->sessions()[0]->durationSeconds());
    }

    public function test_a_new_boot_in_the_events_closes_old_sessions_at_their_last_contact(): void
    {
        $this->feed($this->event('login', self::MAC, $this->at('10:00:00')));

        // Power cut: the router comes back 40 minutes later with a new boot id.
        $this->feed($this->event('login', 'AA:AA:AA:AA:AA:02', $this->at('10:40:00'), boot: 'boot2'));

        [$old, $new] = $this->sessions();
        $this->assertSame(SessionEndReason::Reboot, $old->end_reason);
        $this->assertEquals($this->at('10:00:00'), $old->ended_at);
        $this->assertSame(0, $old->durationSeconds());
        $this->assertTrue($new->isOpen());
        $this->assertSame('boot2', $this->router->fresh()->sessions_boot_id);
    }

    public function test_a_device_that_logs_in_again_after_the_reboot_gets_a_fresh_session(): void
    {
        $this->feed($this->event('login', self::MAC, $this->at('10:00:00')));
        $this->feed($this->event('login', self::MAC, $this->at('10:40:00'), boot: 'boot2'));

        $this->assertCount(2, $this->sessions());
        $this->assertSame([SessionEndReason::Reboot, null], array_map(fn (HotspotSession $s) => $s->end_reason, $this->sessions()));
    }

    public function test_a_header_only_sync_with_a_new_boot_closes_sessions_in_the_job(): void
    {
        $this->feed($this->event('login', self::MAC, $this->at('11:55:00')));
        $this->router->update(['boot_id' => 'boot2']);

        (new ProcessRouterBatch($this->router->id))->handle(
            app(HotspotEventProcessor::class),
            app(RouterSnapshotProcessor::class),
            app(SessionBuilder::class),
        );

        [$session] = $this->sessions();
        $this->assertSame(SessionEndReason::Reboot, $session->end_reason);
        $this->assertEquals($this->at('11:55:00'), $session->ended_at);
    }

    public function test_a_snapshot_of_the_new_boot_cannot_keep_the_old_boots_session_alive(): void
    {
        $this->feed($this->event('login', self::MAC, $this->at('10:00:00')));
        $this->router->update(['boot_id' => 'boot2']);
        $builder = app(SessionBuilder::class);

        $builder->reconcileBoot($this->router->id);
        $builder->applySnapshot($this->snapshot([self::MAC], $this->at('11:00:00'), uptime: 120));

        [$old, $new] = $this->sessions();
        $this->assertEquals($this->at('10:00:00'), $old->ended_at);
        $this->assertEquals($this->at('10:58:00'), $new->started_at);
        $this->assertTrue($new->isOpen());
    }

    public function test_reconciling_the_same_boot_twice_is_harmless(): void
    {
        $this->feed($this->event('login', self::MAC, $this->at('11:55:00')));
        $this->router->update(['boot_id' => 'boot2']);
        $builder = app(SessionBuilder::class);

        $builder->reconcileBoot($this->router->id);
        $builder->reconcileBoot($this->router->id);

        $this->assertCount(1, $this->sessions());
        $this->assertSame(SessionEndReason::Reboot, $this->sessions()[0]->end_reason);
    }

    public function test_the_first_boot_seen_closes_nothing(): void
    {
        $this->applySnapshot([self::MAC], $this->at('11:00:00'));

        app(SessionBuilder::class)->reconcileBoot($this->router->id);

        $this->assertTrue($this->sessions()[0]->isOpen());
        $this->assertSame('boot1', $this->router->fresh()->sessions_boot_id);
    }

    public function test_a_second_worker_waits_for_the_router_lock_and_never_runs_beside_the_first(): void
    {
        config(['network.sessions.lock_wait_seconds' => 0]);
        $event = $this->event('login', self::MAC, $this->at('10:00:00'));
        $held = Cache::lock("router:{$this->router->id}:sessions", 30);
        $this->assertTrue($held->get());

        try {
            $this->expectException(LockTimeoutException::class);
            $this->feed($event);
        } finally {
            $this->assertSame([], $this->sessions());
            $held->release();
        }
    }

    public function test_events_applied_while_the_lock_was_held_are_built_by_the_next_run(): void
    {
        $this->event('login', self::MAC, $this->at('10:00:00'));
        $this->event('logout', self::MAC, $this->at('10:20:00'));
        $held = Cache::lock("router:{$this->router->id}:sessions", 30);
        $this->assertTrue($held->get());

        try {
            app(HotspotEventProcessor::class)->process($this->router->id);
            $this->fail('The build should have timed out on the held lock.');
        } catch (LockTimeoutException) {
            // The job died after the events were marked processed.
        } finally {
            $held->release();
        }

        $this->assertSame([], $this->sessions());

        app(HotspotEventProcessor::class)->process($this->router->id);

        $this->assertSame(1200, $this->sessions()[0]->durationSeconds());
        $this->assertSame(0, HotspotEvent::query()->whereNull('sessions_built_at')->count());
    }

    public function test_a_late_event_of_an_older_boot_leaves_the_new_boots_sessions_alone(): void
    {
        $this->feed($this->event('login', self::MAC, $this->at('10:00:00')));
        $this->feed($this->event('login', 'AA:AA:AA:AA:AA:02', $this->at('10:40:00'), boot: 'boot2'));

        $this->feed($this->event('logout', self::MAC, $this->at('10:10:00'), data: ['uptime' => '10m']));

        [$old, $new] = $this->sessions();
        $this->assertEquals($this->at('10:10:00'), $old->ended_at);
        $this->assertTrue($new->isOpen());
        $this->assertNull($new->end_reason);
        $this->assertSame('boot2', $this->router->fresh()->sessions_boot_id);
    }

    public function test_the_lock_is_released_after_a_build_and_another_router_is_never_blocked(): void
    {
        $other = Router::factory()->create();
        $this->assertTrue(Cache::lock("router:{$other->id}:sessions", 30)->get());

        $this->feed($this->event('login', self::MAC, $this->at('10:00:00')));
        $this->feed($this->event('login', 'AA:AA:AA:AA:AA:02', $this->at('10:01:00')));

        $this->assertCount(2, $this->sessions());
        $this->assertTrue(Cache::lock("router:{$this->router->id}:sessions", 30)->get());
    }

    public function test_two_overlapping_batches_for_one_router_do_not_duplicate_sessions(): void
    {
        $login = $this->event('login', self::MAC, $this->at('10:00:00'));
        $logout = $this->event('logout', self::MAC, $this->at('10:30:00'));
        $again = $this->event('login', self::MAC, $this->at('11:00:00'));

        $this->feed($login, $logout);
        $this->feed($logout, $again);
        $this->feed($login, $logout, $again);

        $this->assertCount(2, $this->sessions());
    }

    public function test_a_subscriber_linked_device_keeps_its_owner_on_the_session(): void
    {
        $this->device(self::MAC, $owner = User::factory()->create()->id);

        $this->applySnapshot([self::MAC], $this->at('11:00:00'));

        $this->assertSame($owner, $this->sessions()[0]->subscriber_id);
    }

    public function test_a_snapshot_resolves_devices_it_has_not_seen_before(): void
    {
        $this->applySnapshot(['aa-bb-cc-dd-ee-ff'], $this->at('11:00:00'));

        $this->assertCount(1, $this->sessions());
        $this->assertSame('AA:BB:CC:DD:EE:FF', $this->sessions()[0]->device->mac);
    }

    /** @return array<string, array{0: SimulationScenario}> */
    public static function scenarios(): array
    {
        return [
            'normal' => [SimulationScenario::Normal],
            'clock drift' => [SimulationScenario::ClockDrift],
            'outage' => [SimulationScenario::Outage],
            'reboot storm' => [SimulationScenario::RebootStorm],
            'duplicates' => [SimulationScenario::Duplicates],
        ];
    }

    #[DataProvider('scenarios')]
    public function test_simulated_routers_produce_consistent_sessions(SimulationScenario $scenario): void
    {
        $workspace = Workspace::factory()->create();
        $issued = app(RouterService::class)->create($workspace, 'Sim');
        $target = new SimulatedRouterTarget($issued->router, (string) $workspace->code, $issued->plainKey);
        $plan = new SimulationPlan($scenario, [$target], 40, 80, 30, seed: 7);

        app()->make(SimulationRunner::class, ['transport' => new FakeRouterTransport, 'clock' => SimulationClock::virtual()])->run($plan);

        $sessions = HotspotSession::query()->withoutWorkspaceScope()->orderBy('device_id')->orderBy('started_at')->get();
        $this->assertGreaterThan(0, $sessions->count());
        $this->assertConsistent($sessions);
    }

    /** @param Collection<int, HotspotSession> $sessions */
    private function assertConsistent($sessions): void
    {
        foreach ($sessions->groupBy('device_id') as $own) {
            $this->assertLessThanOrEqual(1, $own->filter->isOpen()->count(), 'one open session per device');

            $previous = null;
            foreach ($own as $session) {
                $this->assertGreaterThanOrEqual(0, $session->durationSeconds() ?? 0, 'no negative duration');
                $this->assertTrue($previous === null || $previous->ended_at !== null && $previous->ended_at->lessThanOrEqualTo($session->started_at), 'no overlap');
                $previous = $session;
            }
        }
    }
}
