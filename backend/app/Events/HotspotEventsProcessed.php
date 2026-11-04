<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * A batch of raw hotspot events was applied to the connected-now table. The
 * ids are handed to session building (BuildSessionsFromEvents).
 */
final class HotspotEventsProcessed
{
    use Dispatchable;

    /**
     * @param  list<int>  $eventIds
     */
    public function __construct(
        public readonly string $routerId,
        public readonly array $eventIds,
    ) {}
}
