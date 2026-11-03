<?php

declare(strict_types=1);

namespace App\Services\Network;

use Carbon\CarbonInterface;

/**
 * A snapshot whose chunks were put back together: the devices that were
 * connected to the router at `takenAt`.
 */
final readonly class AssembledSnapshot
{
    /**
     * @param  list<SnapshotRow>  $rows
     */
    public function __construct(
        public string $routerId,
        public string $workspaceId,
        public CarbonInterface $takenAt,
        public array $rows,
    ) {}
}
