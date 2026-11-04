<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Router;
use App\Models\Workspace;
use App\Services\Router\RouterCommandService;
use App\Services\Router\RouterService;
use App\Testing\Hotspot\FakeRouterTransport;
use App\Testing\Hotspot\HttpRouterTransport;
use App\Testing\Hotspot\SimulatedRouterTarget;
use App\Testing\Hotspot\SimulationClock;
use App\Testing\Hotspot\SimulationPlan;
use App\Testing\Hotspot\SimulationRunner;
use App\Testing\Hotspot\SimulationScenario;
use App\Testing\Hotspot\SimulationVerifier;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Plays virtual MikroTik routers against the real sync endpoint to check the
 * PUSH protocol, load and failure handling without hardware. It writes
 * simulated rows to the database, so it never runs in production.
 */
class HotspotSimulate extends Command
{
    protected $signature = 'hotspot:simulate
        {--router= : Router id to speak for; its key is rotated for the run (default: a throwaway workspace and routers)}
        {--routers=1 : How many throwaway routers to create when --router is not given}
        {--subscribers=461}
        {--devices=1149}
        {--duration=60 : Minutes of traffic (simulated minutes unless --url is given)}
        {--scenario=normal : normal, outage, duplicates, clock-drift, reboot-storm or rejected-batch}
        {--seed=1}
        {--interval=50 : Seconds between sync cycles}
        {--url= : Post to this base URL over HTTP and in real time; omit to run in-process on a virtual clock}';

    protected $description = 'Simulate MikroTik routers pushing to the hotspot sync endpoint';

    public function handle(RouterService $routerService, RouterCommandService $commands, SimulationVerifier $verifier): int
    {
        if (app()->environment('production')) {
            $this->error('hotspot:simulate writes simulated data and refuses to run in production.');

            return self::FAILURE;
        }

        $scenario = SimulationScenario::tryFrom((string) $this->option('scenario'));

        if ($scenario === null) {
            $this->error('Unknown scenario. Use one of: '.implode(', ', SimulationScenario::values()));

            return self::FAILURE;
        }

        $url = (string) $this->option('url');
        $clock = $url === '' ? SimulationClock::virtual() : SimulationClock::real();

        try {
            $targets = $this->targets($routerService);
            $transport = $url === '' ? new FakeRouterTransport : new HttpRouterTransport($url);
            $report = (new SimulationRunner($transport, $commands, $verifier, $clock))->run($this->plan($scenario, $targets));
        } finally {
            Carbon::setTestNow();
        }

        $this->table(['metric', 'value'], array_map(null, array_keys($report->toArray()), array_values($report->toArray())));
        $report->passes() ? $this->info('Simulation passed.') : $this->error('Simulation found problems, see the report.');

        return $report->passes() ? self::SUCCESS : self::FAILURE;
    }

    /** @param  non-empty-list<SimulatedRouterTarget>  $targets */
    private function plan(SimulationScenario $scenario, array $targets): SimulationPlan
    {
        return new SimulationPlan(
            scenario: $scenario,
            routers: $targets,
            subscribers: max(1, (int) $this->option('subscribers')),
            devices: max((int) $this->option('subscribers'), (int) $this->option('devices')),
            durationMinutes: max(1, (int) $this->option('duration')),
            seed: (int) $this->option('seed'),
            intervalSeconds: max(1, (int) $this->option('interval')),
        );
    }

    /** @return non-empty-list<SimulatedRouterTarget> */
    private function targets(RouterService $routerService): array
    {
        $id = (string) $this->option('router');

        if ($id !== '') {
            $router = Router::query()->withoutWorkspaceScope()->findOrFail($id);
            $issued = $routerService->rotateKeyHash($router);

            return [new SimulatedRouterTarget($router, $this->workspaceCode($router), $issued->plainKey)];
        }

        $workspace = Workspace::factory()->create();

        return array_map(function (int $number) use ($routerService, $workspace): SimulatedRouterTarget {
            $issued = $routerService->create($workspace, "Simulated router {$number}");

            return new SimulatedRouterTarget($issued->router, (string) $workspace->code, $issued->plainKey);
        }, range(1, max(1, (int) $this->option('routers'))));
    }

    private function workspaceCode(Router $router): string
    {
        return (string) Workspace::query()->whereKey($router->workspace_id)->value('code');
    }
}
