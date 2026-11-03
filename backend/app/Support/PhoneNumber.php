<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Canonical phone form used as a matching key between imported subscribers and
 * the phone claim returned by the SSO provider.
 */
final class PhoneNumber
{
    public static function normalize(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $normalized = preg_replace('/[\s\-().]+/', '', trim($phone));

        return $normalized === null || $normalized === '' ? null : $normalized;
    }
}
