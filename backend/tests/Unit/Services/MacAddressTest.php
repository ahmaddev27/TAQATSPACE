<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Support\Network\MacAddress;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MacAddressTest extends TestCase
{
    /** @return array<string, array{0: ?string, 1: ?string}> */
    public static function spellings(): array
    {
        return [
            'lowercase colons' => ['aa:bb:cc:dd:ee:ff', 'AA:BB:CC:DD:EE:FF'],
            'dashes' => ['aa-bb-cc-dd-ee-ff', 'AA:BB:CC:DD:EE:FF'],
            'dots' => ['aabb.ccdd.eeff', 'AA:BB:CC:DD:EE:FF'],
            'bare' => ['AABBCCDDEEFF', 'AA:BB:CC:DD:EE:FF'],
            'padded' => [' aa:bb:cc:dd:ee:ff ', 'AA:BB:CC:DD:EE:FF'],
            'too short' => ['aa:bb:cc', null],
            'not hex' => ['zz:bb:cc:dd:ee:ff', null],
            'null' => [null, null],
        ];
    }

    #[DataProvider('spellings')]
    public function test_normalize(?string $input, ?string $expected): void
    {
        $this->assertSame($expected, MacAddress::normalize($input));
    }

    public function test_detects_randomized_macs_by_the_locally_administered_bit(): void
    {
        $this->assertTrue(MacAddress::isRandomized('02:00:00:00:00:01'));
        $this->assertTrue(MacAddress::isRandomized('DA:A1:19:00:00:01'));
        $this->assertFalse(MacAddress::isRandomized('AC:BB:CC:DD:EE:FF'));
        $this->assertFalse(MacAddress::isRandomized('00:1A:2B:3C:4D:5E'));
    }
}
