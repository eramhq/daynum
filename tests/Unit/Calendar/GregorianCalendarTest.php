<?php

declare(strict_types=1);

namespace Daynum\Tests\Unit\Calendar;

use Daynum\Calendar\Gregorian\GregorianCalendar;
use Daynum\Exception\InvalidDateException;
use PHPUnit\Framework\TestCase;

final class GregorianCalendarTest extends TestCase
{
    /**
     * @dataProvider knownAnchors
     */
    public function testKnownJdnAnchors(int $year, int $month, int $day, int $expectedJdn): void
    {
        $calendar = GregorianCalendar::instance();
        $this->assertSame($expectedJdn, $calendar->toJdn($year, $month, $day));
        $this->assertSame([$year, $month, $day], $calendar->fromJdn($expectedJdn));
    }

    /**
     * @return iterable<string, array{int,int,int,int}>
     */
    public static function knownAnchors(): iterable
    {
        // Astronomical anchors cross-checked against Reingold–Dershowitz and
        // multiple online JDN converters.
        yield 'J2000 epoch'   => [2000,  1,  1, 2451545];
        yield 'Unix epoch'    => [1970,  1,  1, 2440588];
        yield 'Y2K leap day'  => [2000,  2, 29, 2451604];
        yield 'today'         => [2026,  4,  8, 2461139];
    }

    /**
     * @dataProvider leapYears
     */
    public function testLeapYear(int $year, bool $expected): void
    {
        $this->assertSame($expected, GregorianCalendar::instance()->isLeapYear($year));
    }

    /**
     * @return iterable<string, array{int,bool}>
     */
    public static function leapYears(): iterable
    {
        yield 'div 4 not 100'     => [2024, true];
        yield 'div 100 not 400'   => [1900, false];
        yield 'div 400'           => [2000, true];
        yield 'not div 4'         => [2023, false];
        yield '2100 not leap'     => [2100, false];
        yield '2400 leap'         => [2400, true];
        yield 'year 0 is leap'    => [0, true];
        yield 'year -1 not leap'  => [-1, false];
        yield 'year -4 is leap'   => [-4, true];
    }

    public function testDaysInMonth(): void
    {
        $c = GregorianCalendar::instance();
        $this->assertSame(31, $c->daysInMonth(2026, 1));
        $this->assertSame(28, $c->daysInMonth(2026, 2));
        $this->assertSame(29, $c->daysInMonth(2024, 2));
        $this->assertSame(30, $c->daysInMonth(2026, 4));
        $this->assertSame(31, $c->daysInMonth(2026, 12));
    }

    public function testInvalidDayThrows(): void
    {
        $this->expectException(InvalidDateException::class);
        GregorianCalendar::instance()->toJdn(2026, 2, 30);
    }

    public function testInvalidMonthThrows(): void
    {
        $this->expectException(InvalidDateException::class);
        GregorianCalendar::instance()->toJdn(2026, 13, 1);
    }

    public function testName(): void
    {
        $this->assertSame('gregorian', GregorianCalendar::instance()->name());
    }

    public function testMonthsInYear(): void
    {
        $this->assertSame(12, GregorianCalendar::instance()->monthsInYear(2026));
    }

    /**
     * @dataProvider dayOfYearCases
     */
    public function testDayOfYear(int $year, int $month, int $day, int $expected): void
    {
        $this->assertSame($expected, GregorianCalendar::instance()->dayOfYear($year, $month, $day));
    }

    /**
     * @return iterable<string, array{int,int,int,int}>
     */
    public static function dayOfYearCases(): iterable
    {
        // Non-leap year boundaries.
        yield 'non-leap Jan 1'    => [2023, 1, 1, 1];
        yield 'non-leap Feb 28'   => [2023, 2, 28, 59];
        yield 'non-leap Mar 1'    => [2023, 3, 1, 60];
        yield 'non-leap Dec 31'   => [2023, 12, 31, 365];

        // Leap year boundaries — the +1 leap bump only kicks in for month > 2,
        // so Feb 29 itself comes from the cumulative table directly.
        yield 'leap Feb 28'       => [2024, 2, 28, 59];
        yield 'leap Feb 29'       => [2024, 2, 29, 60];
        yield 'leap Mar 1'        => [2024, 3, 1, 61];
        yield 'leap Dec 31'       => [2024, 12, 31, 366];

        // Negative-year proleptic dates. Year -4 is divisible by 4 and not by
        // 100, so it's a leap year under the proleptic rule.
        yield 'negative year Jan 1' => [-4, 1, 1, 1];
        yield 'negative leap Mar 1' => [-4, 3, 1, 61];

        // Year 0 (proleptic; div 400, leap).
        yield 'year 0 leap Mar 1' => [0, 3, 1, 61];
    }
}
