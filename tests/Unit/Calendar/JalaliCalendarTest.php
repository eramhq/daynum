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
