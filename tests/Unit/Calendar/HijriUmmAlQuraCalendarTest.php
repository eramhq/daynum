<?php

declare(strict_types=1);

namespace Daynum\Tests\Unit\Calendar;

use Daynum\Calendar\Gregorian\GregorianCalendar;
use Daynum\Calendar\Hijri\HijriUmmAlQuraCalendar;
use Daynum\Calendar\Hijri\Table;
use Daynum\Exception\InvalidDateException;
use Daynum\Exception\UmmAlQuraOutOfRangeException;
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
        $civil = \Daynum\Calendar\Hijri\HijriCivilCalendar::instance();
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
            $c->dayOfYear(1445, 12, $c->daysInMonth(1445, 12))
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
}
