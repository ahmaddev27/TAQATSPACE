<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Support\Network\ConcurrencySweep;
use PHPUnit\Framework\TestCase;

class ConcurrencySweepTest extends TestCase
{
    public function test_no_intervals_means_no_peak(): void
    {
        $this->assertSame(0, ConcurrencySweep::peak([]));
    }

    public function test_overlapping_intervals_count_together(): void
    {
        $this->assertSame(3, ConcurrencySweep::peak([[0, 100], [10, 50], [20, 30], [60, 70]]));
    }

    public function test_an_interval_ending_when_another_starts_does_not_overlap_it(): void
    {
        $this->assertSame(1, ConcurrencySweep::peak([[0, 10], [10, 20]]));
    }

    public function test_empty_intervals_are_ignored(): void
    {
        $this->assertSame(1, ConcurrencySweep::peak([[5, 5], [0, 10]]));
    }
}
