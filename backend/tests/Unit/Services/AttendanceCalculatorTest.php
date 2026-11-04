<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\Network\AttendanceCalculator;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class AttendanceCalculatorTest extends TestCase
{
    private const GAZA = 'Asia/Gaza';

    private AttendanceCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calculator = new AttendanceCalculator;
    }

    /** A wall-clock time in the given zone (UTC when omitted). */
    private function at(string $time, string $zone = 'UTC'): CarbonImmutable
    {
        return CarbonImmutable::parse($time, $zone);
    }

    private function utc(string $time): CarbonImmutable
    {
        return CarbonImmutable::parse($time, 'UTC');
    }

    /** @param list<int> $slots */
    private function bitmapOf(array $slots): int
    {
        return array_reduce($slots, static fn (int $carry, int $slot): int => $carry | (1 << $slot), 0);
    }

    public function test_two_overlapping_devices_count_once(): void
    {
        $days = $this->calculator->calculate([
            [$this->at('2026-01-10 09:00'), $this->at('2026-01-10 10:00')],
            [$this->at('2026-01-10 09:30'), $this->at('2026-01-10 10:30')],
        ], 'UTC');

        $day = $days['2026-01-10'];
        $this->assertSame(90, $day->activeActualMinutes);
        $this->assertSame(90, $day->daySpanMinutes);
        $this->assertSame(3, $day->halfHourSlots);
        $this->assertSame(2, $day->hourBucketHours);
        $this->assertSame($this->bitmapOf([18, 19, 20]), $day->slotsBitmap);
    }

    public function test_a_session_inside_another_is_absorbed(): void
    {
        $days = $this->calculator->calculate([
            [$this->at('2026-01-10 09:00'), $this->at('2026-01-10 12:00')],
            [$this->at('2026-01-10 10:00'), $this->at('2026-01-10 10:20')],
        ], 'UTC');

        $this->assertSame(180, $days['2026-01-10']->activeActualMinutes);
    }

    public function test_the_four_modes_with_a_gap_match_a_manual_calculation(): void
    {
        $days = $this->calculator->calculate([
            [$this->at('2026-01-10 08:00'), $this->at('2026-01-10 08:20')],
            [$this->at('2026-01-10 14:10'), $this->at('2026-01-10 15:00')],
        ], 'UTC');

        $day = $days['2026-01-10'];
        $this->assertSame(70, $day->activeActualMinutes);
        $this->assertSame(420, $day->daySpanMinutes);
        $this->assertSame(2, $day->hourBucketHours);
        $this->assertSame(3, $day->halfHourSlots);
        $this->assertSame($this->bitmapOf([16, 28, 29]), $day->slotsBitmap);
        $this->assertSame('2026-01-10 08:00:00', $day->firstSeenAt->format('Y-m-d H:i:s'));
        $this->assertSame('2026-01-10 15:00:00', $day->lastSeenAt->format('Y-m-d H:i:s'));
    }

    public function test_a_session_crossing_midnight_is_split_in_the_workspace_zone(): void
    {
        // 22:40-01:10 Gaza time (UTC+2 in winter) is 20:40Z-23:10Z: one UTC day, two local days.
        $days = $this->calculator->calculate([[$this->utc('2026-01-10 20:40'), $this->utc('2026-01-10 23:10')]], self::GAZA);

        $this->assertSame(['2026-01-10', '2026-01-11'], array_keys($days));
        $this->assertSame(80, $days['2026-01-10']->activeActualMinutes);
        $this->assertSame($this->bitmapOf([45, 46, 47]), $days['2026-01-10']->slotsBitmap);
        $this->assertSame(70, $days['2026-01-11']->activeActualMinutes);
        $this->assertSame($this->bitmapOf([0, 1, 2]), $days['2026-01-11']->slotsBitmap);
    }

    public function test_the_highest_slot_survives_as_a_64_bit_integer(): void
    {
        $days = $this->calculator->calculate([[$this->at('2026-01-10 23:30'), $this->at('2026-01-11 00:00')]], 'UTC');

        $this->assertSame(1 << 47, $days['2026-01-10']->slotsBitmap);
        $this->assertSame(1, $days['2026-01-10']->halfHourSlots);
        $this->assertSame(1, $days['2026-01-10']->hourBucketHours);
        $this->assertCount(1, $days);
    }

    public function test_a_full_day_sets_all_48_slots(): void
    {
        $days = $this->calculator->calculate([[$this->at('2026-01-10 00:00'), $this->at('2026-01-11 00:00')]], 'UTC');

        $this->assertSame((1 << 48) - 1, $days['2026-01-10']->slotsBitmap);
        $this->assertSame([1440, 48, 24], [$days['2026-01-10']->activeActualMinutes, $days['2026-01-10']->halfHourSlots, $days['2026-01-10']->hourBucketHours]);
    }

    public function test_a_session_ending_on_a_slot_boundary_does_not_touch_the_next_slot(): void
    {
        $days = $this->calculator->calculate([[$this->at('2026-01-10 10:00'), $this->at('2026-01-10 10:30')]], 'UTC');

        $this->assertSame($this->bitmapOf([20]), $days['2026-01-10']->slotsBitmap);
    }

    public function test_an_instant_session_counts_as_presence_without_minutes(): void
    {
        $days = $this->calculator->calculate([[$this->at('2026-01-10 10:10'), $this->at('2026-01-10 10:10')]], 'UTC');

        $this->assertSame(0, $days['2026-01-10']->activeActualMinutes);
        $this->assertSame($this->bitmapOf([20]), $days['2026-01-10']->slotsBitmap);
    }

    public function test_the_spring_forward_day_is_23_hours_and_its_missing_wall_hour_has_no_slots(): void
    {
        // Gaza 2026-03-28: 02:00 jumps to 03:00 (23 h day). Wall half-hours 02:00-03:00 are slots 4 and 5.
        $days = $this->calculator->calculate([[$this->utc('2026-03-27 22:00'), $this->utc('2026-03-28 21:00')]], self::GAZA);

        $day = $days['2026-03-28'];
        $this->assertSame(23 * 60, $day->activeActualMinutes);
        $this->assertSame(23 * 60, $day->daySpanMinutes);
        $this->assertSame(46, $day->halfHourSlots);
        $this->assertSame(23, $day->hourBucketHours);
        $this->assertSame(((1 << 48) - 1) & ~$this->bitmapOf([4, 5]), $day->slotsBitmap);
        $this->assertCount(1, $days);
    }

    public function test_the_fall_back_day_is_25_hours_and_the_repeated_hour_folds_onto_the_same_slots(): void
    {
        // Gaza 2026-10-24: 02:00 falls back to 01:00 (25 h day); wall 01:00-02:00 happens twice.
        $days = $this->calculator->calculate([[$this->utc('2026-10-23 21:00'), $this->utc('2026-10-24 22:00')]], self::GAZA);

        $day = $days['2026-10-24'];
        $this->assertSame(25 * 60, $day->activeActualMinutes);
        $this->assertSame(25 * 60, $day->daySpanMinutes);
        $this->assertSame(48, $day->halfHourSlots);
        $this->assertSame(24, $day->hourBucketHours);
        $this->assertCount(1, $days);
    }

    public function test_time_in_both_passes_of_the_repeated_hour_counts_once_in_slots_but_fully_in_minutes(): void
    {
        // 01:30 EEST -> 02:00 EEST (30 min), then 01:00 EET -> 01:30 EET (30 min): 60 real minutes.
        $days = $this->calculator->calculate([[$this->utc('2026-10-23 22:30'), $this->utc('2026-10-23 23:30')]], self::GAZA);

        $day = $days['2026-10-24'];
        $this->assertSame(60, $day->activeActualMinutes);
        $this->assertSame($this->bitmapOf([2, 3]), $day->slotsBitmap);
        $this->assertSame(2, $day->halfHourSlots);
    }

    public function test_a_session_after_the_spring_jump_reads_the_new_wall_clock(): void
    {
        // 03:00-03:30 EEST on 2026-03-28 is 00:00Z-00:30Z.
        $days = $this->calculator->calculate([[$this->utc('2026-03-28 00:00'), $this->utc('2026-03-28 00:30')]], self::GAZA);

        $this->assertSame($this->bitmapOf([6]), $days['2026-03-28']->slotsBitmap);
    }

    public function test_a_session_across_the_spring_jump_spans_real_time(): void
    {
        // 01:45 EET -> 03:15 EEST is 30 real minutes (23:45Z -> 00:15Z): wall 01:45-02:00 and 03:00-03:15.
        $days = $this->calculator->calculate([[$this->utc('2026-03-27 23:45'), $this->utc('2026-03-28 00:15')]], self::GAZA);

        $day = $days['2026-03-28'];
        $this->assertSame(30, $day->activeActualMinutes);
        $this->assertSame($this->bitmapOf([3, 6]), $day->slotsBitmap);
    }

    public function test_rows_are_ordered_by_date_whatever_the_input_order(): void
    {
        $days = $this->calculator->calculate([
            [$this->at('2026-01-12 10:00'), $this->at('2026-01-12 11:00')],
            [$this->at('2026-01-10 10:00'), $this->at('2026-01-10 11:00')],
        ], 'UTC');

        $this->assertSame(['2026-01-10', '2026-01-12'], array_keys($days));
    }

    public function test_no_intervals_means_no_days(): void
    {
        $this->assertSame([], $this->calculator->calculate([], self::GAZA));
    }
}
