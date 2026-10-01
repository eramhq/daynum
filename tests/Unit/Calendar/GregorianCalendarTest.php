<?php

declare(strict_types=1);

namespace Eram\Daynum\Tests\Unit\Calendar;

use Eram\Daynum\Calendar\Gregorian\GregorianCalendar;
use Eram\Daynum\Exception\InvalidDateException;
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

    /**
     * @dataProvider monthLengthsByYear
     *
     * @param list<int> $expected
     */
    public function testDaysInMonthForEveryMonth(int $year, array $expected): void
    {
        $c = GregorianCalendar::instance();
        $actual = [];
        for ($m = 1; $m <= 12; $m++) {
            $actual[] = $c->daysInMonth($year, $m);
        }
        $this->assertSame($expected, $actual);
    }

    /**
     * @return iterable<string, array{int, list<int>}>
     */
    public static function monthLengthsByYear(): iterable
    {
        $common = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
        $leap = [31, 29, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];

        yield '1900 (div 100, common)'  => [1900, $common];
        yield '2000 (div 400, leap)'    => [2000, $leap];
        yield '2023 (common)'           => [2023, $common];
        yield '2024 (div 4, leap)'      => [2024, $leap];
        yield '2100 (div 100, common)'  => [2100, $common];
        yield '-4 (proleptic leap)'     => [-4, $leap];
    }

    /**
     * Every month's last day is accepted by toJdn and the following day is
     * rejected; cross-checked against PHP's own proleptic Gregorian `t`.
     *
     * @dataProvider monthLengthsByYear
     *
     * @param list<int> $expected
     */
    public function testToJdnAcceptsExactlyTheMonthLength(int $year, array $expected): void
    {
        $c = GregorianCalendar::instance();
        foreach ($expected as $i => $dim) {
            $month = $i + 1;
            if ($year > 0) {
                $php = (int) (new \DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month)))->format('t');
                $this->assertSame($php, $dim, "{$year}-{$month} vs DateTime");
            }
            $jdn = $c->toJdn($year, $month, $dim);
            $this->assertSame([$year, $month, $dim], $c->fromJdn($jdn));
            try {
                $c->toJdn($year, $month, $dim + 1);
                $this->fail("{$year}-{$month}-" . ($dim + 1) . ' should be rejected');
            } catch (InvalidDateException $e) {
                $this->assertStringContainsString("day must be in [1, {$dim}]", $e->getMessage());
            }
        }
    }

    public function testInvalidDayThrows(): void
    {
        $this->expectException(InvalidDateException::class);
        GregorianCalendar::instance()->toJdn(2026, 2, 30);
    }

    public function testInvalidMonthThrows(): void
    {
        $this->expectException(InvalidDateException::class);
        $this->expectExceptionMessage('month must be in [1, 12]');
        GregorianCalendar::instance()->toJdn(2026, 13, 1);
    }

    public function testMonthZeroThrows(): void
    {
        $this->expectException(InvalidDateException::class);
        $this->expectExceptionMessage('month must be in [1, 12]');
        GregorianCalendar::instance()->toJdn(2026, 0, 1);
    }

    /**
     * @dataProvider outOfRangeYears
     */
    public function testYearOutsideSupportedRangeThrows(int $year): void
    {
        $this->expectException(InvalidDateException::class);
        $this->expectExceptionMessage('year must be in [-9999, 9999]');
        GregorianCalendar::instance()->toJdn($year, 1, 1);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function outOfRangeYears(): iterable
    {
        yield 'MAX_YEAR + 1' => [GregorianCalendar::MAX_YEAR + 1];
        yield 'MIN_YEAR - 1' => [GregorianCalendar::MIN_YEAR - 1];
    }

    public function testSupportedRangeEndpoints(): void
    {
        $c = GregorianCalendar::instance();
        // -9999-01-01 and 9999-12-31 proleptic Gregorian.
        $this->assertSame([-1930999, 5373484], $c->supportedRange());
        $this->assertSame([-9999, 1, 1], $c->fromJdn(-1930999));
        $this->assertSame([9999, 12, 31], $c->fromJdn(5373484));
    }

    public function testInstanceIsASingleton(): void
    {
        $this->assertSame(GregorianCalendar::instance(), GregorianCalendar::instance());
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
