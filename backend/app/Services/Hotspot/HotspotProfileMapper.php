<?php

declare(strict_types=1);

namespace App\Services\Hotspot;

use App\Models\InternetPackage;

class HotspotProfileMapper
{
    private const BYTES_PER_GB = 1_073_741_824;

    public function fromPackage(InternetPackage $package, ?int $validityDays = null): HotspotProfile
    {
        $capped = ! $package->is_unlimited && $package->data_limit_gb > 0;

        return new HotspotProfile(
            name: 'cws-'.substr(str_replace('-', '', (string) $package->getKey()), 0, 12),
            rateLimit: $package->speed_mbps > 0 ? "{$package->speed_mbps}M/{$package->speed_mbps}M" : '',
            sharedUsers: 1,
            limitBytesTotal: $capped ? $package->data_limit_gb * self::BYTES_PER_GB : null,
            validityDays: $validityDays,
        );
    }
}
