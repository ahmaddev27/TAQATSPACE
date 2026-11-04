<?php

declare(strict_types=1);

namespace App\Support\Network;

use DateTimeImmutable;
use DateTimeZone;
use Exception;

/**
 * Calendar days in a workspace time zone, as `Y-m-d` strings and UNIX
 * timestamps. A local day is not always 24 hours: around a daylight saving
 * change it is 23 or 25.
 */
final class LocalDays
{
    public function dateOf(int $timestamp, DateTimeZone $timezone): string
    {
        return (new DateTimeImmutable('@'.$timestamp))->setTimezone($timezone)->format('Y-m-d');
    }

    /** The first instant of the local day; a midnight that does not exist resolves to the first instant after it. */
    public function startOf(string $date, DateTimeZone $timezone): int
    {
        return (new DateTimeImmutable($date.' 00:00:00', $timezone))->getTimestamp();
    }

    public function next(string $date): string
    {
        return (new DateTimeImmutable($date, new DateTimeZone('UTC')))->modify('+1 day')->format('Y-m-d');
    }

    /** The local midnight read as if the clock were UTC; wall-clock seconds of the day are measured from it. */
    public function wallEpoch(string $date): int
    {
        return (new DateTimeImmutable($date.' 00:00:00', new DateTimeZone('UTC')))->getTimestamp();
    }

    public function zone(?string $name): DateTimeZone
    {
        try {
            return new DateTimeZone($name ?: 'UTC');
        } catch (Exception) {
            return new DateTimeZone('UTC');
        }
    }
}
