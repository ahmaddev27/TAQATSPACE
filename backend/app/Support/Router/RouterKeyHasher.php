<?php

declare(strict_types=1);

namespace App\Support\Router;

/**
 * Generates router keys and hashes them for storage (same SHA-256 scheme as
 * partner API keys). The plaintext is shown once and never persisted.
 */
final class RouterKeyHasher
{
    private const KEY_BYTES = 24;

    public static function generate(): string
    {
        return bin2hex(random_bytes(self::KEY_BYTES));
    }

    public static function hash(string $plain): string
    {
        return hash('sha256', $plain);
    }

    public static function matches(string $plain, string $hash): bool
    {
        return hash_equals($hash, self::hash($plain));
    }
}
