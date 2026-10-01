<?php

declare(strict_types=1);

namespace Eram\Daynum\Tests\Unit\Calendar;

use Eram\Daynum\Calendar\Gregorian\GregorianCalendar;
use Eram\Daynum\Calendar\Jalali\JalaliCalendar;
use Eram\Daynum\Exception\InvalidDateException;
use PHPUnit\Framework\TestCase;

final class JalaliCalendarTest extends TestCase
{
    /**
     * @dataProvider knownJalaliGregorianPairs
     */
    public function testRoundTripThroughGregorian(int $gy, int $gm, int $gd, int $jy, int $jm, int $jd): void
    {
        $greg = GregorianCalendar::instance();
        $jal = JalaliCalendar::instance();

        $jdn = $greg->toJdn($gy, $gm, $gd);
        $this->assertSame([$jy, $jm, $jd], $jal->fromJdn($jdn));
        $this->assertSame($jdn, $jal->toJdn($jy, $jm, $jd));
    }

    /**
     * Pairs cross-checked against ICU `fa_IR@calendar=persian`, jalaali-js,
     * and date-fns-jalali.
     *
     * @return iterable<string, array{int,int,int,int,int,int}>
     */
    public static function knownJalaliGregorianPairs(): iterable
    {
        yield 'Nowruz 1405'        => [2026,  3, 21, 1405,  1,  1];
        yield 'today'              => [2026,  4,  8, 1405,  1, 19];
        yield 'Esfand 1404 end'    => [2026,  3, 20, 1404, 12, 29];
        yield 'Mid-Mehr 1404'      => [2025, 10, 15, 1404,  7, 23];
        yield 'Nowruz 1400'        => [2021,  3, 21, 1400,  1,  1];
        yield 'Nowruz 1370'        => [1991,  3, 21, 1370,  1,  1];
        yield 'Nowruz 1300'        => [1921,  3, 21, 1300,  1,  1];
        yield 'Tir 4 1350'         => [1971,  6, 25, 1350,  4,  4];
    }

    /**
     * @dataProvider leapYears
     */
    public function testLeapYear(int $year, bool $expected): void
    {
        $this->assertSame($expected, JalaliCalendar::instance()->isLeapYear($year));
    }

    /**
     * Known Birashk-cycle leap years from the plan.
     *
     * @return iterable<string, array{int,bool}>
     */
    public static function leapYears(): iterable
    {
        yield '1370 leap'     => [1370, true];
        yield '1375 leap'     => [1375, true];
        yield '1379 leap'     => [1379, true];
        yield '1383 leap'     => [1383, true];
        yield '1387 leap'     => [1387, true];
        yield '1391 leap'     => [1391, true];
        yield '1395 leap'     => [1395, true];
        yield '1399 leap'     => [1399, true];
        yield '1403 leap'     => [1403, true];
        yield '1408 leap'     => [1408, true];
        yield '1412 leap'     => [1412, true];
        yield '1416 leap'     => [1416, true];
        yield '1420 leap'     => [1420, true];
        yield '1424 leap'     => [1424, true];
        yield '1428 leap'     => [1428, true];
        yield '1432 leap'     => [1432, true];
        yield '1400 not leap' => [1400, false];
        yield '1404 not leap' => [1404, false];
        yield '1405 not leap' => [1405, false];
    }

    public function testDaysInMonth(): void
    {
        $c = JalaliCalendar::instance();
        // First 6 months: 31 days
        for ($m = 1; $m <= 6; $m++) {
            $this->assertSame(31, $c->daysInMonth(1405, $m), "Month {$m}");
        }
        // Months 7..11: 30 days
        for ($m = 7; $m <= 11; $m++) {
            $this->assertSame(30, $c->daysInMonth(1405, $m), "Month {$m}");
        }
        // Esfand: 29 in common year, 30 in leap year
        $this->assertSame(29, $c->daysInMonth(1405, 12));
        $this->assertSame(30, $c->daysInMonth(1403, 12));
    }

    public function testJalaliYearRangeRejected(): void
    {
        $this->expectException(InvalidDateException::class);
        JalaliCalendar::instance()->toJdn(3178, 1, 1);
    }

    public function testJalaliDayOverflowRejected(): void
    {
        $this->expectException(InvalidDateException::class);
        // 1405 is not a leap year → Esfand has 29 days
        JalaliCalendar::instance()->toJdn(1405, 12, 30);
    }

    public function testJalaliLeapYearAllowsEsfand30(): void
    {
        $jdn = JalaliCalendar::instance()->toJdn(1403, 12, 30);
        $this->assertSame([1403, 12, 30], JalaliCalendar::instance()->fromJdn($jdn));
    }

    /**
     * @dataProvider overflowingDays
     */
    public function testDayPastMonthLengthRejected(int $year, int $month, int $day, int $maxDay): void
    {
        $this->expectException(InvalidDateException::class);
        $this->expectExceptionMessage("day must be in [1, {$maxDay}]");
        JalaliCalendar::instance()->toJdn($year, $month, $day);
    }

    /**
     * @return iterable<string, array{int,int,int,int}>
     */
    public static function overflowingDays(): iterable
    {
        for ($m = 1; $m <= 6; $m++) {
            yield "1405-{$m}-32" => [1405, $m, 32, 31];
        }
        for ($m = 7; $m <= 11; $m++) {
            yield "1405-{$m}-31" => [1405, $m, 31, 30];
        }
        yield 'leap Esfand 31'   => [1403, 12, 31, 30];
        yield 'common Esfand 30' => [1405, 12, 30, 29];
    }

    /**
     * Farvardin 1 and the leap flag on both sides of every Birashk
     * break-point (the years where jalCal's cycle walk changes segment),
     * plus the first and last supported years and the three Birashk-vs-ICU
     * leap disagreements. Values pinned from the jalaali-js algorithm.
     *
     * @dataProvider nowruzAnchors
     */
    public function testNowruzAndLeapAcrossBreakPoints(int $jy, int $gy, int $gm, int $gd, bool $leap): void
    {
        $jal = JalaliCalendar::instance();
        $jdn = GregorianCalendar::instance()->toJdn($gy, $gm, $gd);
        $this->assertSame($jdn, $jal->toJdn($jy, 1, 1));
        $this->assertSame([$jy, 1, 1], $jal->fromJdn($jdn));
        $this->assertSame([$jy - 1, 12, $jal->daysInMonth($jy - 1, 12)], $jal->fromJdn($jdn - 1));
        $this->assertSame($leap, $jal->isLeapYear($jy));
    }

    /**
     * @return iterable<string, array{int,int,int,int,bool}>
     */
    public static function nowruzAnchors(): iterable
    {
        yield '1 (MIN_YEAR)' => [1, 622, 3, 22, false];
        yield '2'            => [2, 623, 3, 22, false];
        yield '3'            => [3, 624, 3, 21, false];
        yield '4'            => [4, 625, 3, 21, true];
        yield '5'            => [5, 626, 3, 22, false];
        yield '8'            => [8, 629, 3, 21, false];
        yield '9 (break)'    => [9, 630, 3, 21, true];
        yield '10'           => [10, 631, 3, 22, false];
        yield '33'           => [33, 654, 3, 21, true];
        yield '37'           => [37, 658, 3, 21, false];
        yield '38 (break)'   => [38, 659, 3, 21, true];
        yield '198'          => [198, 819, 3, 21, false];
        yield '199 (break)'  => [199, 820, 3, 20, true];
        yield '1110'         => [1110, 1731, 3, 21, false];
        yield '1111 (break)' => [1111, 1732, 3, 20, true];
        yield '1176'         => [1176, 1797, 3, 20, true];
        yield '1177'         => [1177, 1798, 3, 21, false];
        yield '1178'         => [1178, 1799, 3, 21, false];
        yield '1180'         => [1180, 1801, 3, 21, false];
        yield '1181 (break)' => [1181, 1802, 3, 21, true];
        yield '1205'         => [1205, 1826, 3, 21, true];
        yield '1206'         => [1206, 1827, 3, 22, false];
        yield '1209'         => [1209, 1830, 3, 21, false];
        yield '1210 (break)' => [1210, 1831, 3, 21, true];
        yield '1502'         => [1502, 2123, 3, 21, true];
        yield '1503'         => [1503, 2124, 3, 21, false];
        yield '1601'         => [1601, 2222, 3, 21, true];
        yield '1602'         => [1602, 2223, 3, 22, false];
        yield '1630'         => [1630, 2251, 3, 21, true];
        yield '1634'         => [1634, 2255, 3, 21, false];
        yield '1635 (break)' => [1635, 2256, 3, 20, true];
        yield '2059'         => [2059, 2680, 3, 20, false];
        yield '2060 (break)' => [2060, 2681, 3, 20, true];
        yield '2096'         => [2096, 2717, 3, 21, false];
        yield '2097 (break)' => [2097, 2718, 3, 21, true];
        yield '2191'         => [2191, 2812, 3, 20, false];
        yield '2192 (break)' => [2192, 2813, 3, 20, true];
        yield '2455'         => [2455, 3076, 3, 20, false];
        yield '2456 (break)' => [2456, 3077, 3, 20, true];
        yield '3176'         => [3176, 3797, 3, 20, false];
        yield '3177 (MAX)'   => [3177, 3798, 3, 20, false];
    }

    /**
     * Over the whole supported range, the gap between consecutive
     * Farvardin 1s must be 366 days exactly when the earlier year is leap.
     * jalCal derives the leap flag and the Farvardin-1 day independently,
     * so any slip in either breaks this identity.
     */
    public function testYearLengthMatchesLeapFlagForEveryYear(): void
    {
        $c = JalaliCalendar::instance();
        $start = $c->toJdn(JalaliCalendar::MIN_YEAR, 1, 1);
        for ($y = JalaliCalendar::MIN_YEAR; $y < JalaliCalendar::MAX_YEAR; $y++) {
            $next = $c->toJdn($y + 1, 1, 1);
            $this->assertSame($c->isLeapYear($y) ? 366 : 365, $next - $start, "Jalali year {$y}");
            $start = $next;
        }
    }

    public function testSupportedRangeEndpoints(): void
    {
        $c = JalaliCalendar::instance();
        // 1-01-01 AP = 622-03-22 CE; 3177-12-29 AP = 3799-03-19 CE.
        $this->assertSame([1948321, 3108694], $c->supportedRange());
        $this->assertSame([1, 1, 1], $c->fromJdn(1948321));
        $this->assertSame([3177, 12, 29], $c->fromJdn(3108694));
    }

    public function testMonthsInYear(): void
    {
        $this->assertSame(12, JalaliCalendar::instance()->monthsInYear(1405));
    }

    public function testInstanceIsASingleton(): void
    {
        $this->assertSame(JalaliCalendar::instance(), JalaliCalendar::instance());
    }

    /**
     * The per-instance jalCal memo only stores the documented year range,
     * so out-of-range callers (isLeapYear / fromJdn accept any year) can't
     * grow it without bound.
     */
    public function testJalCalCacheHoldsOnlySupportedYears(): void
    {
        $c = new JalaliCalendar();
        foreach ([JalaliCalendar::MIN_YEAR - 1, JalaliCalendar::MIN_YEAR, JalaliCalendar::MAX_YEAR, JalaliCalendar::MAX_YEAR + 1] as $y) {
            $c->isLeapYear($y);
        }
        $cache = (new \ReflectionProperty(JalaliCalendar::class, 'jalCalCache'))->getValue($c);
        $this->assertIsArray($cache);
        $this->assertSame([JalaliCalendar::MIN_YEAR, JalaliCalendar::MAX_YEAR], array_keys($cache));
    }

    public function testName(): void
    {
        $this->assertSame('jalali', JalaliCalendar::instance()->name());
    }

    /**
     * @dataProvider dayOfYearCases
     */
    public function testDayOfYear(int $year, int $month, int $day, int $expected): void
    {
        $this->assertSame($expected, JalaliCalendar::instance()->dayOfYear($year, $month, $day));
    }

    /**
     * @return iterable<string, array{int,int,int,int}>
     */
    public static function dayOfYearCases(): iterable
    {
        // Year-start.
        yield 'Farvardin 1 → 1'             => [1405, 1, 1, 1];
        // End of the 31-day-month run.
        yield 'Shahrivar 31 → 186'          => [1405, 6, 31, 186];
        // First 30-day-month transition.
        yield 'Mehr 1 → 187'                => [1405, 7, 1, 187];
        // Non-leap year length (1405 is not leap — existing test confirms).
        yield 'Esfand 29 non-leap → 365'    => [1405, 12, 29, 365];
        // Leap-year length via $day (1403 is confirmed leap above).
        yield 'Esfand 30 leap → 366'        => [1403, 12, 30, 366];
        // MAX_YEAR boundary.
        yield 'MAX_YEAR Farvardin 1 → 1'    => [JalaliCalendar::MAX_YEAR, 1, 1, 1];
    }
}
