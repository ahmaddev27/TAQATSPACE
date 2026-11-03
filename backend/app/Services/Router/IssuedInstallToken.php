<?php

declare(strict_types=1);

namespace App\Services\Router;

use Carbon\CarbonInterface;

/** A freshly issued install token with the command the owner pastes into RouterOS. */
final readonly class IssuedInstallToken
{
    public function __construct(
        public string $url,
        public string $command,
        public CarbonInterface $expiresAt,
    ) {}
}
