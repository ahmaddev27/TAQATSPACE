<?php

declare(strict_types=1);

namespace App\Testing\Hotspot;

use App\Enums\RouterCommandVerb;
use App\Services\Router\RouterCommandService;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Drives the simulated routers cycle by cycle through the real sync endpoint
 * and applies the failure scenario. Traffic is generated for the planned
 * duration, then the routers keep syncing until nothing is left to deliver.
 */
final class SimulationRunner
{
    private const OUTAGE_START_CYCLE = 6;

    private const OUTAGE_SECONDS = 3600;

    private const TAIL_CYCLES = 6;

    private const MAX_DRAIN_CYCLES = 300;

    private const CLOCK_DRIFT_SECONDS = 3600;

    /** @var array<string, RouterSimulator> router id => simulator */
    private array $simulators = [];

    /** @var array<string, list<SimulatedDevice>> router id => its devices */
    private array $devices = [];

    private int $sent = 0;

    private int $delivered = 0;

    private int $unreachable = 0;

    private int $duplicatesSent = 0;

    /** @var array<int, int> */
    private array $statusCounts = [];

    private Randomizer $random;

    private FlakyRouterTransport $transport;

    public function __construct(
        RouterTransport $transport,
        private readonly RouterCommandService $commands,
        private readonly SimulationVerifier $verifier,
        private readonly SimulationClock $clock,
    ) {
        $this->transport = new FlakyRouterTransport($transport);
    }

    public function run(SimulationPlan $plan): SimulationReport
    {
        $this->random = new Randomizer(new Mt19937($plan->seed));
        $this->prepare($plan);

        $cycles = $this->cycleCount($plan);

        for ($cycle = 0; $cycle < $cycles; $cycle++) {
            $this->applyScenario($plan, $cycle, $cycles);
            $this->runCycle($plan, $cycle, $cycles);
        }

        $this->transport->setOnline(true);
        $this->drain($plan);

        return $this->report($plan);
    }

    private function prepare(SimulationPlan $plan): void
    {
        $dataset = SimulationDataset::generate($plan->seed, $plan->subscribers, $plan->devices, count($plan->routers));

        foreach ($plan->routers as $index => $target) {
            $id = (string) $target->router->getKey();
            $this->simulators[$id] = new RouterSimulator(
                $target->workspaceCode,
                $target->plainKey,
                $index,
                $plan->seed,
                $this->clockSkew($plan->scenario, $index),
                $index % 2 === 1,
            );
            $this->devices[$id] = array_values(array_filter($dataset, static fn (SimulatedDevice $device): bool => $device->routerIndex === $index));
            $this->queueCommands($target);
        }
    }

    private function clockSkew(SimulationScenario $scenario, int $index): int
    {
        if ($scenario !== SimulationScenario::ClockDrift) {
            return 0;
        }

        return $index % 2 === 0 ? self::CLOCK_DRIFT_SECONDS : -self::CLOCK_DRIFT_SECONDS;
    }

    /** A few real commands so the run also exercises the `C|` and `A|` lines. */
    private function queueCommands(SimulatedRouterTarget $target): void
    {
        foreach (['simvoucher1', 'simvoucher2'] as $name) {
            $this->commands->enqueue($target->router, RouterCommandVerb::UpsertUser, ['username' => $name, 'password' => 'pass1234']);
        }

        $this->commands->enqueue($target->router, RouterCommandVerb::UpsertProfile, ['name' => 'sim-profile', 'rate_limit' => '2M/2M']);
    }

    private function cycleCount(SimulationPlan $plan): int
    {
        $cycles = max(1, intdiv($plan->durationMinutes * 60, $plan->intervalSeconds));

        if ($plan->scenario !== SimulationScenario::Outage) {
            return $cycles;
        }

        return max($cycles, self::OUTAGE_START_CYCLE + $this->outageCycles($plan) + self::TAIL_CYCLES);
    }

    private function outageCycles(SimulationPlan $plan): int
    {
        return (int) ceil(self::OUTAGE_SECONDS / $plan->intervalSeconds);
    }

    private function applyScenario(SimulationPlan $plan, int $cycle, int $cycles): void
    {
        if ($plan->scenario === SimulationScenario::Outage) {
            $start = self::OUTAGE_START_CYCLE;
            $this->transport->setOnline($cycle < $start || $cycle >= $start + $this->outageCycles($plan));
        }

        if ($plan->scenario === SimulationScenario::RebootStorm && $cycle === $this->rebootCycle($cycles)) {
            foreach ($this->simulators as $simulator) {
                $simulator->reboot();
            }
        }
    }

    /** The cycle before is silent so every router has delivered everything it recorded. */
    private function rebootCycle(int $cycles): int
    {
        return max(1, intdiv($cycles, 2));
    }

    private function runCycle(SimulationPlan $plan, int $cycle, int $cycles): void
    {
        $now = $this->clock->now();
        $silent = $plan->scenario === SimulationScenario::RebootStorm && $cycle === $this->rebootCycle($cycles) - 1;

        foreach ($this->simulators as $id => $simulator) {
            if (! $silent) {
                $this->generateTraffic($simulator, $this->devices[$id], $now);
            }

            $this->record($simulator->tick($this->transport, $now));

            if ($plan->scenario === SimulationScenario::Duplicates && $cycle % 2 === 0) {
                $this->record($simulator->replay($this->transport, $cycle % 4 === 2), true);
            }
        }

        $this->clock->advance($plan->intervalSeconds);
    }

    /**
     * About 5% of a router's devices log in or out each cycle.
     *
     * @param  list<SimulatedDevice>  $devices
     */
    private function generateTraffic(RouterSimulator $simulator, array $devices, int $now): void
    {
        if ($devices === []) {
            return;
        }

        for ($i = 0, $count = max(1, intdiv(count($devices), 20)); $i < $count; $i++) {
            $device = $devices[$this->random->getInt(0, count($devices) - 1)];
            $simulator->record($simulator->isActive($device) ? 'logout' : 'login', $device, $now);
        }
    }

    private function record(?TickResult $result, bool $replay = false): void
    {
        if ($result === null) {
            return;
        }

        $this->sent++;

        if ($result->status === null) {
            $this->unreachable++;

            return;
        }

        $this->statusCounts[$result->status] = ($this->statusCounts[$result->status] ?? 0) + 1;
        $this->delivered += $result->delivered ? 1 : 0;
        $this->duplicatesSent += $replay && $result->delivered ? $result->events : 0;
    }

    private function drain(SimulationPlan $plan): void
    {
        for ($i = 0; $i < self::MAX_DRAIN_CYCLES && $this->anyPending(); $i++) {
            $now = $this->clock->now();

            foreach ($this->simulators as $simulator) {
                $this->record($simulator->tick($this->transport, $now));
            }

            $this->clock->advance($plan->intervalSeconds);
        }
    }

    private function anyPending(): bool
    {
        foreach ($this->simulators as $simulator) {
            if ($simulator->hasPending()) {
                return true;
            }
        }

        return false;
    }

    private function report(SimulationPlan $plan): SimulationReport
    {
        $result = $this->verifier->verify($this->simulators);
        $generated = array_sum(array_map(static fn (RouterSimulator $simulator): int => count($simulator->generatedEvents()), $this->simulators));

        return new SimulationReport(
            scenario: $plan->scenario,
            routers: count($this->simulators),
            batchesSent: $this->sent,
            batchesDelivered: $this->delivered,
            unreachable: $this->unreachable,
            statusCounts: $this->statusCounts,
            eventsGenerated: $generated,
            eventsStored: $result['stored'],
            eventsLost: $result['lost'],
            duplicatesSent: $this->duplicatesSent,
            duplicatesStored: $result['duplicatesStored'],
            seqGaps: $result['seqGaps'],
            maxClockErrorSeconds: $result['maxClockError'],
            commandsQueued: $result['commandsQueued'],
            commandsAcknowledged: $result['commandsAcknowledged'],
        );
    }
}
