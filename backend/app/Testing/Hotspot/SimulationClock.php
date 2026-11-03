<?php

declare(strict_types=1);

namespace App\Testing\Hotspot;

use Illuminate\Support\Carbon;

/**
 * Virtual mode freezes the application clock and moves it forward, so an
 * hour-long scenario costs no waiting and the server's cache TTLs, throttle
 * windows and command expiry see the same time as the simulated routers.
 * Real mode sleeps, for runs against a live server.
 */
final class SimulationClock
{
    private function __construct(private readonly bool $virtual) {}

    public static function virtual(): self
    {
        Carbon::setTestNow(Carbon::now());

        return new self(true);
    }

    public static function real(): self
    {
        return new self(false);
    }

    public function now(): int
    {
        return Carbon::now()->getTimestamp();
    }

    public function advance(int $seconds): void
    {
        if ($this->virtual) {
            Carbon::setTestNow(Carbon::now()->addSeconds($seconds));

            return;
        }

        sleep($seconds);
    }
}
