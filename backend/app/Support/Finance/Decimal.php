<?php

declare(strict_types=1);

namespace App\Support\Finance;

/**
 * Money read back from SQL as a two-place decimal string. MariaDB returns a
 * DECIMAL sum as an exact string; SQLite (the test database) sums decimals as
 * floats, so 0.70 + 0.10 can come back as 0.7999999999999999. Both are rounded
 * half away from zero with bcmath; bcadd on its own would truncate to 0.79.
 */
final class Decimal
{
    public static function of(mixed $value): string
    {
        $text = match (true) {
            $value === null || $value === '' => '0',
            // Six places hold any float error far below a cent.
            is_float($value) => sprintf('%.6F', $value),
            default => (string) $value,
        };

        return bcadd($text, str_starts_with($text, '-') ? '-0.005' : '0.005', 2);
    }
}
