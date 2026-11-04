<?php

declare(strict_types=1);

namespace App\Support\Router;

/**
 * Escapes a value for use inside a double-quoted RouterOS string so that user
 * supplied text can never terminate the string or expand a variable/command.
 */
final class RouterOsString
{
    public static function escape(string $value): string
    {
        $clean = preg_replace('/[\x00-\x1F\x7F]+/', ' ', $value) ?? '';

        return preg_replace('/([\\\\"$?])/', '\\\\$1', $clean) ?? '';
    }
}
