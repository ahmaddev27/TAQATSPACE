<?php

declare(strict_types=1);

namespace App\Services\Network;

/** What the scores of one subscriber are computed from, already reduced to plain numbers. */
final class SubscriberActivity
{
    public function __construct(
        public readonly int $daysSinceLastActivity,
        public readonly int $tenureDays,
        /** Months of the window the subscriber has been around for, at least 1. */
        public readonly int $spanMonths,
        /** Sum of the stored monthly attendance rates (AR%) over the window. */
        public readonly float $attendanceRateSum,
        public readonly int $attendanceDays,
        public readonly int $activeMinutes,
        public readonly int $hourBucketHours,
        /** Share of the subscriber's days in the window that a subscription covered, 0 to 1. */
        public readonly float $contractCoverage,
    ) {}
}
