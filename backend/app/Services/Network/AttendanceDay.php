<?php

declare(strict_types=1);

namespace App\Services\Network;

use Carbon\CarbonImmutable;

/** One subscriber-day as the calculator derives it; the four modes come from the same bitmap and intervals. */
final class AttendanceDay
{
    public function __construct(
        public readonly string $date,
        public readonly int $slotsBitmap,
        public readonly int $activeActualMinutes,
        public readonly int $daySpanMinutes,
        public readonly int $hourBucketHours,
        public readonly int $halfHourSlots,
        public readonly CarbonImmutable $firstSeenAt,
        public readonly CarbonImmutable $lastSeenAt,
    ) {}
}
