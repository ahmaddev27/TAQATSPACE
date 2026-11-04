<?php

declare(strict_types=1);

namespace App\Services\Hotspot;

/**
 * A router-side hotspot profile derived from an internet package. Only `name`
 * and `rateLimit` travel in `upsert-profile`; the router forces shared-users=1.
 * The byte cap and validity are enforced by the platform (expiry disables the
 * user), not by the router.
 */
final readonly class HotspotProfile
{
    public function __construct(
        public string $name,
        public string $rateLimit,
        public int $sharedUsers,
        public ?int $limitBytesTotal,
        public ?int $validityDays,
    ) {}
}
