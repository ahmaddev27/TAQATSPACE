<?php

declare(strict_types=1);

namespace Tests\Feature\Network;

use App\Models\HotspotEvent;
use App\Models\Workspace;
use App\Services\Router\RouterService;
use App\Testing\Hotspot\FakeRouterTransport;
use App\Testing\Hotspot\SimulatedRouterTarget;
use App\Testing\Hotspot\SimulationClock;
use App\Testing\Hotspot\SimulationPlan;
use App\Testing\Hotspot\SimulationReport;
use App\Testing\Hotspot\SimulationRunner;
use App\Testing\Hotspot\SimulationScenario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class HotspotSimulationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function simulate(SimulationScenario $scenario, int $routers = 1, int $minutes = 20, int $subscribers = 461, int $devices = 1149): SimulationReport
    {
        $workspace = Workspace::factory()->create();
        $service = app(RouterService::class);
        $targets = array_map(function (int $number) use ($service, $workspace): SimulatedRouterTarget {
            $issued = $service->create($workspace, "Sim {$number}");

            return new SimulatedRouterTarget($issued->router, (string) $workspace->code, $issued->plainKey);
        }, range(1, $routers));

        $plan = new SimulationPlan($scenario, $targets, $subscribers, $devices, $minutes, seed: 11);

        return app()->make(SimulationRunner::class, ['transport' => new FakeRouterTransport, 'clock' => SimulationClock::virtual()])->run($plan);
    }

    private function assertClean(SimulationReport $report): void
    {
        $this->assertGreaterThan(0, $report->eventsGenerated);
        $this->assertSame(0, $report->eventsLost, 'lost events');
        $this->assertSame($report->eventsGenerated, $report->eventsStored + $report->eventsDropped);
        $this->assertSame(0, $report->duplicatesStored);
        $this->assertSame(0, $report->seqGaps);
        $this->assertSame(0, $report->serverErrors());
        $this->assertLessThanOrEqual(SimulationReport::CLOCK_TOLERANCE_SECONDS, $report->maxClockErrorSeconds);
        $this->assertSame($report->commandsQueued, $report->commandsAcknowledged);
        $this->assertTrue($report->passes());
    }

    public function test_normal_operation_with_461_subscribers_and_1149_devices_loses_and_duplicates_nothing(): void
    {
        $report = $this->simulate(SimulationScenario::Normal);

        $this->assertClean($report);
        $this->assertSame(1.0, $report->successRate());
        $this->assertSame(3, $report->commandsQueued);
    }

    public function test_a_one_hour_outage_delivers_everything_after_recovery_in_order_with_correct_times(): void
    {
        $report = $this->simulate(SimulationScenario::Outage);

        $this->assertClean($report);
        $this->assertGreaterThanOrEqual(72, $report->unreachable);
        $this->assertGreaterThan(500, $report->eventsGenerated, 'the outage must buffer more than one batch');
        $this->assertSame(0, $report->eventsDropped);
    }

    public function test_a_batch_the_server_refuses_is_dropped_and_later_data_still_flows(): void
    {
        $report = $this->simulate(SimulationScenario::RejectedBatch);

        $this->assertClean($report);
        $this->assertGreaterThan(0, $report->eventsDropped);
        $this->assertSame($report->eventsGenerated, $report->eventsStored + $report->eventsDropped);
        $this->assertSame(5, $report->statusCounts[422] ?? 0);
        $this->assertSame(0, $report->serverErrors());
    }

    public function test_duplicate_deliveries_are_not_stored_twice(): void
    {
        $report = $this->simulate(SimulationScenario::Duplicates);

        $this->assertClean($report);
        $this->assertGreaterThan(0, $report->duplicatesSent);
        $this->assertSame($report->eventsGenerated, HotspotEvent::query()->count());
    }

    public function test_clock_drift_of_plus_and_minus_one_hour_is_corrected(): void
    {
        $report = $this->simulate(SimulationScenario::ClockDrift, routers: 2);

        $this->assertClean($report);
        $this->assertSame(2, $report->routers);
        $this->assertDatabaseHas('routers', ['clock_offset_seconds' => -3600]);
        $this->assertDatabaseHas('routers', ['clock_offset_seconds' => 3600]);
    }

    public function test_a_reboot_storm_raises_no_server_errors_and_keeps_seq_continuous(): void
    {
        $report = $this->simulate(SimulationScenario::RebootStorm, routers: 5);

        $this->assertClean($report);
        $this->assertSame(5, $report->routers);

        $bootIds = HotspotEvent::query()->pluck('event_uid')->map(static fn (string $uid): string => explode('-', $uid)[0])->unique();
        $this->assertCount(10, $bootIds, 'every router must have events from two boots');
    }

    public function test_the_command_prints_a_passing_report(): void
    {
        $this->artisan('hotspot:simulate', ['--scenario' => 'normal', '--subscribers' => 20, '--devices' => 40, '--duration' => 10])
            ->expectsOutputToContain('Simulation passed.')
            ->assertSuccessful();
    }

    public function test_the_command_refuses_to_run_in_production(): void
    {
        $this->app['env'] = 'production';

        $this->artisan('hotspot:simulate')
            ->expectsOutputToContain('refuses to run in production')
            ->assertFailed();

        $this->assertSame(0, HotspotEvent::query()->count());
    }

    public function test_the_command_rejects_an_unknown_scenario(): void
    {
        $this->artisan('hotspot:simulate', ['--scenario' => 'meteor'])->assertFailed();
    }
}
