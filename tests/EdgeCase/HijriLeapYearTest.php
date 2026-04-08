<?php

declare(strict_types=1);

namespace Daynum\Tests\EdgeCase;

use Daynum\Calendar\Hijri\HijriCivilCalendar;
use PHPUnit\Framework\TestCase;

/**
 * The tabular Hijri calendar's 30-year cycle has leap years in the eleven
 * canonical positions {2, 5, 7, 10, 13, 16, 18, 21, 24, 26, 29} — the
 * "year-16 leap" variant, which is what ICU's `islamic-civil` and
 * Reingold–Dershowitz's "Arithmetic Islamic" calendar implement.
 *
 * These tests nail down the leap pattern against regressions in the
 * `(11*y + 14) mod 30 < 11` formula.
 */
final class HijriLeapYearTest extends TestCase
{
    public function testFirstCycleLeapPattern(): void
    {
        $c = HijriCivilCalendar::instance();
        $leap = [];
        for ($y = 1; $y <= 30; $y++) {
            if ($c->isLeapYear($y)) {
                $leap[] = $y;
            }
        }
        $this->assertSame([2, 5, 7, 10, 13, 16, 18, 21, 24, 26, 29], $leap);
    }

    public function testSecondCycleShiftsByThirty(): void
    {
        $c = HijriCivilCalendar::instance();
        $leap = [];
        for ($y = 31; $y <= 60; $y++) {
            if ($c->isLeapYear($y)) {
                $leap[] = $y - 30; // position within cycle
            }
        }
        $this->assertSame([2, 5, 7, 10, 13, 16, 18, 21, 24, 26, 29], $leap);
    }

    public function testHistoricalLeapYear1430(): void
    {
        // AH 1430 = position 1430 mod 30 = 20 + 10 = ...
        //   1430 / 30 = 47 remainder 20
        // Position 20: NOT in {2,5,7,10,13,16,18,21,24,26,29}.
        // So AH 1430 is NOT leap under year-16 variant.
        $c = HijriCivilCalendar::instance();
        $this->assertFalse($c->isLeapYear(1430));
        $this->assertSame(29, $c->daysInMonth(1430, 12));
    }

    public function testKnownLeapYear1436(): void
    {
        // 1436 mod 30 = 26 → position 26 is leap.
        $c = HijriCivilCalendar::instance();
        $this->assertTrue($c->isLeapYear(1436));
        $this->assertSame(30, $c->daysInMonth(1436, 12));
    }

    public function testLeapCountIn30YearCycleIs11(): void
    {
        // Eleven leap years in every 30-year cycle, for any starting year
        // aligned to a cycle boundary.
        $c = HijriCivilCalendar::instance();
        $count = 0;
        for ($y = 1; $y <= 30; $y++) {
            if ($c->isLeapYear($y)) {
                $count++;
            }
        }
        $this->assertSame(11, $count);
    }

    public function testYearLengthAggregatesOver30Cycle(): void
    {
        // 19 common years * 354 + 11 leap years * 355 = 10631 days
        // per 30-year cycle. This is the defining property of the variant.
        $c = HijriCivilCalendar::instance();
        $total = 0;
        for ($y = 1; $y <= 30; $y++) {
            for ($m = 1; $m <= 12; $m++) {
                $total += $c->daysInMonth($y, $m);
            }
        }
        $this->assertSame(10631, $total);
    }
}
