<?php

declare(strict_types=1);

namespace App\Services\Network;

use Carbon\CarbonInterface;

/**
 * The boot a router's sessions are built under and when its first evidence
 * occurred. Boot ids are opaque, so that moment is what tells a late event of
 * an older boot from the first event of a newer one.
 */
final readonly class BootMark
{
    public function __construct(
        public ?string $id,
        public ?CarbonInterface $since,
    ) {}
}
