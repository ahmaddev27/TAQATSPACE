<?php

declare(strict_types=1);

namespace App\Testing\Hotspot;

use App\Models\Router;

/** A persisted router the simulation speaks for, with its plaintext key (only known at creation). */
final readonly class SimulatedRouterTarget
{
    public function __construct(
        public Router $router,
        public string $workspaceCode,
        public string $plainKey,
    ) {}
}
