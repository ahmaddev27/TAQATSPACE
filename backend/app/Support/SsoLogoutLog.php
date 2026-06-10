<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Log;

/**
 * TEMP diagnostic logger for SSO single-logout — REMOVE once verified.
 *
 * Writes to a dedicated file at debug level so it surfaces regardless of the
 * app's LOG_LEVEL / default channel: storage/logs/sso-logout.log.
 *
 * @param  array<string, mixed>  $context
 */
final class SsoLogoutLog
{
    /**
     * @param  array<string, mixed>  $context
     */
    public static function write(string $point, array $context = []): void
    {
        Log::build([
            'driver' => 'single',
            'path' => storage_path('logs/sso-logout.log'),
            'level' => 'debug',
        ])->debug("[sso-logout] {$point}", $context);
    }
}
