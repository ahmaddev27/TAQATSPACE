<?php

declare(strict_types=1);

namespace App\Testing\Hotspot;

final readonly class SimulationPlan
{
    public const DEFAULT_INTERVAL_SECONDS = 50;

    /**
     * @param  non-empty-list<SimulatedRouterTarget>  $routers
     * @param  int  $durationMinutes  virtual minutes of traffic before the final drain
     */
    public function __construct(
        public SimulationScenario $scenario,
        public array $routers,
        public int $subscribers = 461,
        public int $devices = 1149,
        public int $durationMinutes = 60,
        public int $seed = 1,
        public int $intervalSeconds = self::DEFAULT_INTERVAL_SECONDS,
        public bool $refreshNowOnResend = false,
    ) {}
}
