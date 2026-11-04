<?php

declare(strict_types=1);

namespace App\Testing\Hotspot;

final readonly class SimulatedDevice
{
    public function __construct(
        public string $username,
        public string $mac,
        public string $ip,
        public int $routerIndex,
    ) {}
}
