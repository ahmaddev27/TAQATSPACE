<?php

declare(strict_types=1);

namespace App\Support\Network;

/**
 * Canonical MAC form (`AA:BB:CC:DD:EE:FF`), so every spelling a router or
 * client may report maps to one device row.
 */
final class MacAddress
{
    private const LOCALLY_ADMINISTERED_BIT = 0x02;

    /** @return string|null null when the input is not a 48-bit MAC */
    public static function normalize(?string $mac): ?string
    {
        $hex = strtoupper((string) preg_replace('/[\s:.\-]/', '', (string) $mac));

        if (! preg_match('/^[0-9A-F]{12}$/', $hex)) {
            return null;
        }

        return implode(':', str_split($hex, 2));
    }

    /** Phones rotate "private" addresses that set the locally-administered bit. */
    public static function isRandomized(string $normalizedMac): bool
    {
        return (hexdec(substr($normalizedMac, 0, 2)) & self::LOCALLY_ADMINISTERED_BIT) !== 0;
    }
}
