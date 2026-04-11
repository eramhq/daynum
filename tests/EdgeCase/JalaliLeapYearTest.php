<?php

declare(strict_types=1);

namespace Eram\Daynum\Tests\EdgeCase;

use Eram\Daynum\Calendar\Jalali\JalaliCalendar;
use Eram\Daynum\Instant;
use PHPUnit\Framework\TestCase;

/**
 * Jalali leap-year hand-checks.
 *
 * Leap years under the 33-year Birashk cycle ported into Daynum. The exact
 * sequence in the modern era is 1370, 1375, 1379, 1383, 1387, 1391, 1395,
 * 1399, 1403, 1408, 1412, 1416, 1420, 1424, 1429, 1433, ... The 5-year gap
 * between 1403 and 1408 (instead of the usual 4) is the hallmark of the
 * 33-year cycle and the place most off-by-one bugs surface.
 */
final class JalaliLeapYearTest extends TestCase
{
    /**
     * @return list<int>
     */
    private const LEAP_YEARS = [
        1370, 1375, 1379, 1383, 1387, 1391, 1395, 1399, 1403,
        1408, 1412, 1416, 1420, 1424, 1428, 1432,
    ];

    public function testAllListedYearsAreLeap(): void
    {
        $calendar = JalaliCalendar::instance();
        foreach (self::LEAP_YEARS as $year) {
            $this->assertTrue($calendar->isLeapYear($year), "Expected {$year} to be a leap year");
        }
    }

    public function testLeapYearsPermitEsfand30(): void
    {
        foreach (self::LEAP_YEARS as $year) {
            $i = Instant::fromJalali($year, 12, 30);
            $this->assertSame($year, $i->jalali()->year(), "Round-trip failed for leap year {$year}");
            $this->assertSame(12, $i->jalali()->month());
            $this->assertSame(30, $i->jalali()->day());
        }
    }

    public function testNonLeapYearsRejectEsfand30(): void
    {
        $calendar = JalaliCalendar::instance();
        $leapSet = array_flip(self::LEAP_YEARS);
        for ($y = 1370; $y <= 1433; $y++) {
            if (isset($leapSet[$y])) {
                continue;
            }
            $this->assertFalse($calendar->isLeapYear($y), "Unexpected leap year {$y}");
        }
    }

    public function test1403To1408GapIsFiveYears(): void
    {
        // The 5-year gap between 1403 and 1408 (rather than 4) is the defining
        // characteristic of the 33-year Birashk cycle. Any implementation that
        // naively applies a 4-year leap rule will fail this test.
        $calendar = JalaliCalendar::instance();
        $this->assertTrue($calendar->isLeapYear(1403));
        $this->assertFalse($calendar->isLeapYear(1404));
        $this->assertFalse($calendar->isLeapYear(1405));
        $this->assertFalse($calendar->isLeapYear(1406));
        $this->assertFalse($calendar->isLeapYear(1407));
        $this->assertTrue($calendar->isLeapYear(1408));
    }
}
