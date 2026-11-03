<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\InternetPackage;
use App\Services\Hotspot\HotspotProfileMapper;
use PHPUnit\Framework\TestCase;

class HotspotProfileMapperTest extends TestCase
{
    private function package(array $attributes): InternetPackage
    {
        $package = new InternetPackage($attributes);
        $package->id = '0f8fad5b-d9cb-469f-a165-70867728950e';

        return $package;
    }

    public function test_maps_speed_cap_and_single_device(): void
    {
        $profile = (new HotspotProfileMapper)->fromPackage(
            $this->package(['speed_mbps' => 50, 'data_limit_gb' => 2, 'is_unlimited' => false]),
            30,
        );

        $this->assertSame('cws-0f8fad5bd9cb', $profile->name);
        $this->assertSame('50M/50M', $profile->rateLimit);
        $this->assertSame(1, $profile->sharedUsers);
        $this->assertSame(2 * 1_073_741_824, $profile->limitBytesTotal);
        $this->assertSame(30, $profile->validityDays);
    }

    public function test_unlimited_packages_have_no_byte_cap(): void
    {
        $profile = (new HotspotProfileMapper)->fromPackage(
            $this->package(['speed_mbps' => 0, 'data_limit_gb' => null, 'is_unlimited' => true]),
        );

        $this->assertSame('', $profile->rateLimit);
        $this->assertNull($profile->limitBytesTotal);
        $this->assertNull($profile->validityDays);
    }
}
