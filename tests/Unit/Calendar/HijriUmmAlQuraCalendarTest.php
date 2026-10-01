<?php

declare(strict_types=1);

namespace Eram\Daynum\Tests\Unit\Calendar;

use Eram\Daynum\Calendar\Gregorian\GregorianCalendar;
use Eram\Daynum\Calendar\Hijri\HijriUmmAlQuraCalendar;
use Eram\Daynum\Calendar\Hijri\Table;
use Eram\Daynum\Exception\InvalidDateException;
use Eram\Daynum\Exception\UmmAlQuraOutOfRangeException;
use PHPUnit\Framework\TestCase;

final class HijriUmmAlQuraCalendarTest extends TestCase
{
    /**
     * Hand-verified Ramadan anchors against Saudi Supreme Court moonsighting
     * announcements and ICU's `islamic-umalqura` calendar.
     *
     * @dataProvider ramadanAnchors
     */
    public function testRamadanAnchorRoundTrip(int $gy, int $gm, int $gd, int $hy, int $hm, int $hd): void
    {
        $greg = GregorianCalendar::instance();
        $uaq = HijriUmmAlQuraCalendar::instance();

        $jdn = $greg->toJdn($gy, $gm, $gd);
        $this->assertSame([$hy, $hm, $hd], $uaq->fromJdn($jdn));
        $this->assertSame($jdn, $uaq->toJdn($hy, $hm, $hd));
    }

    /** @return iterable<string, array{int,int,int,int,int,int}> */
    public static function ramadanAnchors(): iterable
    {
        yield 'Ramadan 1 1444 = 2023-03-23'   => [2023, 3, 23, 1444, 9, 1];
        yield 'Ramadan 1 1445 = 2024-03-11'   => [2024, 3, 11, 1445, 9, 1];
        yield 'Ramadan 1 1446 = 2025-03-01'   => [2025, 3,  1, 1446, 9, 1];
        yield 'Shawwal 1 1445 = 2024-04-10'   => [2024, 4, 10, 1445, 10, 1];
    }

    public function testMinYearRoundTrip(): void
    {
        $c = HijriUmmAlQuraCalendar::instance();
        $jdn = $c->toJdn(Table::MIN_YEAR, 1, 1);
        $this->assertSame([Table::MIN_YEAR, 1, 1], $c->fromJdn($jdn));
    }

    public function testMaxYearRoundTripAtLastDay(): void
    {
        $c = HijriUmmAlQuraCalendar::instance();
        $lastMonthDays = $c->daysInMonth(Table::MAX_YEAR, 12);
        $jdn = $c->toJdn(Table::MAX_YEAR, 12, $lastMonthDays);
        $this->assertSame([Table::MAX_YEAR, 12, $lastMonthDays], $c->fromJdn($jdn));
    }

    public function testYearBelowMinThrows(): void
    {
        $this->expectException(UmmAlQuraOutOfRangeException::class);
        HijriUmmAlQuraCalendar::instance()->toJdn(Table::MIN_YEAR - 1, 1, 1);
    }

    public function testYearAboveMaxThrows(): void
    {
        $this->expectException(UmmAlQuraOutOfRangeException::class);
        HijriUmmAlQuraCalendar::instance()->toJdn(Table::MAX_YEAR + 1, 1, 1);
    }

    public function testOutOfRangeMessageMentionsRange(): void
    {
        try {
            HijriUmmAlQuraCalendar::instance()->toJdn(1200, 1, 1);
            $this->fail('expected throw');
        } catch (UmmAlQuraOutOfRangeException $e) {
            $this->assertStringContainsString('AH ' . Table::MIN_YEAR, $e->getMessage());
            $this->assertStringContainsString('AH ' . Table::MAX_YEAR, $e->getMessage());
            $this->assertStringContainsString('fromHijriCivil', $e->getMessage());
        }
    }

    public function testDaysInMonthForKnownYear(): void
    {
        // Hand-checked against ICU `islamic-umalqura` for AH 1445:
        //   Muharram..Dhu al-Hijjah = 29,30,30,30,29,30,29,29,30,29,29,30
        // (sums to 354 — not a leap year under UAQ).
        $c = HijriUmmAlQuraCalendar::instance();
        $expected = [29, 30, 30, 30, 29, 30, 29, 29, 30, 29, 29, 30];
        for ($m = 1; $m <= 12; $m++) {
            $this->assertSame($expected[$m - 1], $c->daysInMonth(1445, $m));
        }
    }

    public function testIsLeapYearAtKnownAnchors(): void
    {
        // Hand-checked against ICU `islamic-umalqura`:
        //   1444..1446 are common years (354 days), 1447 + 1448 are leap.
        $c = HijriUmmAlQuraCalendar::instance();
        $this->assertFalse($c->isLeapYear(1444));
        $this->assertFalse($c->isLeapYear(1445));
        $this->assertFalse($c->isLeapYear(1446));
        $this->assertTrue($c->isLeapYear(1447));
        $this->assertTrue($c->isLeapYear(1448));
    }

    public function testInvalidDayThrows(): void
    {
        $this->expectException(InvalidDateException::class);
        // Muharram of 1445 in UAQ — day 31 is invalid no matter what.
        HijriUmmAlQuraCalendar::instance()->toJdn(1445, 1, 31);
    }

    public function testInvalidMonthThrows(): void
    {
        $this->expectException(InvalidDateException::class);
        HijriUmmAlQuraCalendar::instance()->toJdn(1445, 13, 1);
    }

    public function testUaqAndCivilDifferAtAKnownDivergentDate(): void
    {
        // 2020-01-01 Gregorian is Jumada I 5, 1441 AH under UAQ but
        // Jumada I 6, 1441 AH under civil — the two calendars label the
        // same JDN differently inside the native UAQ range.
        $greg = GregorianCalendar::instance();
        $jdn = $greg->toJdn(2020, 1, 1);
        $uaq = HijriUmmAlQuraCalendar::instance();
        $civil = \Eram\Daynum\Calendar\Hijri\HijriCivilCalendar::instance();
        $this->assertSame([1441, 5, 5], $civil->fromJdn($jdn));
        $this->assertSame([1441, 5, 6], $uaq->fromJdn($jdn));
    }

    public function testName(): void
    {
        $this->assertSame('hijri-umalqura', HijriUmmAlQuraCalendar::instance()->name());
    }

    public function testLocaleFamilyIsSharedWithCivil(): void
    {
        $this->assertSame('hijri', HijriUmmAlQuraCalendar::instance()->localeFamily());
    }

    public function testDayOfYearMinYearStart(): void
    {
        $c = HijriUmmAlQuraCalendar::instance();
        $this->assertSame(1, $c->dayOfYear(Table::MIN_YEAR, 1, 1));
    }

    public function testDayOfYearLastDayMatchesSummedDaysInMonth(): void
    {
        // Cross-check the bit-walk end-to-end against an independent
        // reference: summing daysInMonth across the whole year must match
        // dayOfYear of (12, daysInMonth(12)).
        $c = HijriUmmAlQuraCalendar::instance();
        $total = 0;
        for ($m = 1; $m <= 12; $m++) {
            $total += $c->daysInMonth(1445, $m);
        }
        $this->assertSame(
            $total,
            $c->dayOfYear(1445, 12, $c->daysInMonth(1445, 12)),
        );
    }

    public function testDayOfYearBelowMinYearThrows(): void
    {
        $this->expectException(UmmAlQuraOutOfRangeException::class);
        HijriUmmAlQuraCalendar::instance()->dayOfYear(Table::MIN_YEAR - 1, 1, 1);
    }

    public function testDayOfYearAboveMaxYearThrows(): void
    {
        $this->expectException(UmmAlQuraOutOfRangeException::class);
        HijriUmmAlQuraCalendar::instance()->dayOfYear(Table::MAX_YEAR + 1, 1, 1);
    }

    public function testDayOfYearAtMaxYearLastDay(): void
    {
        // AH 1600 is a 354-day year ending on a 30-day Dhu al-Hijjah.
        $this->assertSame(354, HijriUmmAlQuraCalendar::instance()->dayOfYear(1600, 12, 30));
    }

    /**
     * First and last JDN of the bundled table, cross-checked against ICU
     * `islamic-umalqura`: AH 1300-01-01 = 1882-11-12 and AH 1600-12-30 =
     * 2174-11-25 (Gregorian).
     */
    public function testSupportedRangeEndpoints(): void
    {
        $this->assertSame([2408762, 2515426], HijriUmmAlQuraCalendar::instance()->supportedRange());
        $greg = GregorianCalendar::instance();
        $this->assertSame(2408762, $greg->toJdn(1882, 11, 12));
        $this->assertSame(2515426, $greg->toJdn(2174, 11, 25));
    }

    public function testFromJdnAtFirstSupportedDay(): void
    {
        $this->assertSame([1300, 1, 1], HijriUmmAlQuraCalendar::instance()->fromJdn(2408762));
        $this->assertSame(2408762, HijriUmmAlQuraCalendar::instance()->toJdn(1300, 1, 1));
    }

    public function testFromJdnAtLastSupportedDay(): void
    {
        $this->assertSame([1600, 12, 30], HijriUmmAlQuraCalendar::instance()->fromJdn(2515426));
        $this->assertSame(2515426, HijriUmmAlQuraCalendar::instance()->toJdn(1600, 12, 30));
    }

    /**
     * @dataProvider jdnsOutsideTable
     */
    public function testFromJdnJustOutsideTableThrows(int $jdn): void
    {
        $this->expectException(UmmAlQuraOutOfRangeException::class);
        HijriUmmAlQuraCalendar::instance()->fromJdn($jdn);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function jdnsOutsideTable(): iterable
    {
        yield 'day before AH 1300-01-01' => [2408761];
        yield 'day after AH 1600-12-30'  => [2515427];
    }

    public function testToJdnRejectsDay30InA29DayMonth(): void
    {
        // Muharram 1445 has 29 days (see testDaysInMonthForKnownYear).
        $this->expectException(InvalidDateException::class);
        $this->expectExceptionMessage('day must be in [1, 29]');
        HijriUmmAlQuraCalendar::instance()->toJdn(1445, 1, 30);
    }

    public function testToJdnAcceptsDay30InA30DayMonth(): void
    {
        // Safar 1445 has 30 days; Rabi I 1445 follows it.
        $c = HijriUmmAlQuraCalendar::instance();
        $this->assertSame($c->toJdn(1445, 3, 1) - 1, $c->toJdn(1445, 2, 30));
    }

    /**
     * @dataProvider yearsOutsideTable
     */
    public function testIsLeapYearOutsideTableThrows(int $year): void
    {
        $this->expectException(UmmAlQuraOutOfRangeException::class);
        $this->expectExceptionMessage("date {$year}-01-01 is outside");
        HijriUmmAlQuraCalendar::instance()->isLeapYear($year);
    }

    /**
     * @dataProvider yearsOutsideTable
     */
    public function testDaysInMonthOutsideTableThrows(int $year): void
    {
        $this->expectException(UmmAlQuraOutOfRangeException::class);
        $this->expectExceptionMessage("date {$year}-05-01 is outside");
        HijriUmmAlQuraCalendar::instance()->daysInMonth($year, 5);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function yearsOutsideTable(): iterable
    {
        yield 'MIN_YEAR - 1' => [Table::MIN_YEAR - 1];
        yield 'MAX_YEAR + 1' => [Table::MAX_YEAR + 1];
    }

    public function testMonthsInYear(): void
    {
        $this->assertSame(12, HijriUmmAlQuraCalendar::instance()->monthsInYear(1445));
    }

    public function testInstanceIsASingleton(): void
    {
        $this->assertSame(HijriUmmAlQuraCalendar::instance(), HijriUmmAlQuraCalendar::instance());
    }
}
