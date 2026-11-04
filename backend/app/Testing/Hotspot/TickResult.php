<?php

declare(strict_types=1);

namespace App\Testing\Hotspot;

/** Outcome of one sync cycle: `status` is null when the server was unreachable. */
final readonly class TickResult
{
    public function __construct(
        public ?int $status,
        public bool $delivered,
        public int $events = 0,
    ) {}

    public function serverError(): bool
    {
        return $this->status !== null && $this->status >= 500;
    }
}
