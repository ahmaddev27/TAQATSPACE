<?php

declare(strict_types=1);

namespace Tests\Concerns;

use Illuminate\Support\Facades\DB;

/**
 * Lets services that bucket months with MySQL's DATE_FORMAT run under sqlite.
 * On MySQL/MariaDB the real function exists, so nothing is registered there and
 * the production query is exercised untouched.
 */
trait EmulatesMysqlDateFormat
{
    protected function emulateMysqlDateFormat(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            return;
        }

        DB::connection()->getPdo()->sqliteCreateFunction(
            'DATE_FORMAT',
            static function (?string $date, string $format): ?string {
                if ($date === null) {
                    return null;
                }

                $php = str_replace(['%Y', '%m', '%d'], ['Y', 'm', 'd'], $format);

                return date($php, (int) strtotime($date));
            },
            2,
        );
    }
}
