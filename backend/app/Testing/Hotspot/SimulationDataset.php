<?php

declare(strict_types=1);

namespace App\Testing\Hotspot;

use InvalidArgumentException;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Deterministic subscriber and device generator: the same seed always yields
 * the same data, so a failing run can be replayed exactly.
 */
final class SimulationDataset
{
    /**
     * Every subscriber gets one device; the rest are spread at random. A
     * subscriber and all their devices live on the same router.
     *
     * @return list<SimulatedDevice>
     */
    public static function generate(int $seed, int $subscribers, int $devices, int $routers): array
    {
        if ($subscribers < 1 || $routers < 1 || $devices < $subscribers) {
            throw new InvalidArgumentException('Need at least one router, one subscriber and one device per subscriber.');
        }

        $random = new Randomizer(new Mt19937($seed));
        $owners = range(0, $subscribers - 1);

        for ($extra = $subscribers; $extra < $devices; $extra++) {
            $owners[] = $random->getInt(0, $subscribers - 1);
        }

        $prefix = $random->getInt(0, 255);

        return array_map(
            static fn (int $owner, int $index): SimulatedDevice => new SimulatedDevice(
                username: sprintf('sub%05d', $owner),
                mac: sprintf('02:%02X:%02X:%02X:%02X:%02X', $prefix, ($index >> 24) & 255, ($index >> 16) & 255, ($index >> 8) & 255, $index & 255),
                ip: sprintf('10.%d.%d.%d', (($index + 1) >> 16) & 255, (($index + 1) >> 8) & 255, ($index + 1) & 255),
                routerIndex: $owner % $routers,
            ),
            $owners,
            array_keys($owners),
        );
    }
}
