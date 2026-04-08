<?php

declare(strict_types=1);

namespace Daynum\Tests\Unit\Calendar;

use Daynum\Calendar\Gregorian\GregorianCalendar;
use Daynum\Calendar\Hijri\HijriCivilCalendar;
use Daynum\Exception\InvalidDateException;
use PHPUnit\Framework\TestCase;

final class HijriCivilCalendarTest extends TestCase
{
    public function testEpochAnchorAh0101MapsToJdn1948440(): void
    {
        $this->assertSame(
            1948440,
            HijriCivilCalendar::instance()->toJdn(1, 1, 1)
        );
        $this->assertSame(
            [1, 1, 1],
            HijriCivilCalendar::instance()->fromJdn(1948440)
        );
    }

    public function testSanityAnchor2000Gregorian(): void
    {
        // Widely cited anchor: 2000-04-06 Gregorian = AH 1421-01-01 tabular.
        $greg = GregorianCalendar::instance()->toJdn(2000, 4, 6);
        $this->assertSame(
            [1421, 1, 1],
            HijriCivilCalendar::instance()->fromJdn($greg)
        );
        $this->assertSame(
            $greg,
            HijriCivilCalendar::instance()->toJdn(1421, 1, 1)
        );
    }

    public function testLeapYearPatternInFirstCycle(): void
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

    public function testLeapYearPatternCrossesCycleBoundaryCleanly(): void
    {
        $c = HijriCivilCalendar::instance();
        $this->assertFalse($c->isLeapYear(30), 'year 30 not leap');
        $this->assertFalse($c->isLeapYear(31), 'year 31 = cycle-2 position 1');
        $this->assertTrue($c->isLeapYear(32), 'year 32 = cycle-2 position 2 (leap)');
    }

    public function testOddMonthsHave30DaysEvenMonthsHave29Days(): void
    {
        $c = HijriCivilCalendar::instance();
        // Non-leap year (1 is not leap).
        for ($m = 1; $m <= 11; $m++) {
            $expected = ($m % 2 === 1) ? 30 : 29;
            $this->assertSame($expected, $c->daysInMonth(1, $m), "Month {$m}");
        }
        $this->assertSame(29, $c->daysInMonth(1, 12), 'Dhu al-Hijjah in common year');
    }

    public function testDhuAlHijjahHas30DaysInLeapYear(): void
    {
        $c = HijriCivilCalendar::instance();
        $this->assertTrue($c->isLeapYear(2));
        $this->assertSame(30, $c->daysInMonth(2, 12));
    }

    public function testAh1IsNotLeap(): void
    {
        $c = HijriCivilCalendar::instance();
        $this->assertFalse($c->isLeapYear(1));
        $yearLen = 0;
        for ($m = 1; $m <= 12; $m++) {
            $yearLen += $c->daysInMonth(1, $m);
        }
        $this->assertSame(354, $yearLen, 'AH 1 should be 354 days');
    }

    public function testAh2IsLeapAnd355Days(): void
    {
        $c = HijriCivilCalendar::instance();
        $this->assertTrue($c->isLeapYear(2));
        $yearLen = 0;
        for ($m = 1; $m <= 12; $m++) {
            $yearLen += $c->daysInMonth(2, $m);
        }
        $this->assertSame(355, $yearLen);
    }

    public function testYearOutOfRangeThrows(): void
    {
        $this->expectException(InvalidDateException::class);
        HijriCivilCalendar::instance()->toJdn(0, 1, 1);
    }

    public function testNegativeYearThrows(): void
    {
        $this->expectException(InvalidDateException::class);
        HijriCivilCalendar::instance()->toJdn(-1, 1, 1);
    }

    public function testMonthOutOfRangeThrows(): void
    {
        $this->expectException(InvalidDateException::class);
        HijriCivilCalendar::instance()->toJdn(1, 13, 1);
    }

    public function testMuharramDay31Throws(): void
    {
        $this->expectException(InvalidDateException::class);
        HijriCivilCalendar::instance()->toJdn(1, 1, 31);
    }

    public function testDhuAlHijjah30ThrowsInCommonYearAh1(): void
    {
        $this->expectException(InvalidDateException::class);
        HijriCivilCalendar::instance()->toJdn(1, 12, 30);
    }

    public function testDhuAlHijjah30AllowedInLeapYearAh2(): void
    {
        $jdn = HijriCivilCalendar::instance()->toJdn(2, 12, 30);
        $this->assertSame(
            [2, 12, 30],
            HijriCivilCalendar::instance()->fromJdn($jdn)
        );
    }

    public function testRoundTripAcrossWideSampleOfYears(): void
    {
        $c = HijriCivilCalendar::instance();
        // Sample every leap and non-leap year endpoint over a wide range.
        foreach ([1, 2, 30, 100, 500, 1000, 1421, 1445, 2000, 5000, 9666] as $y) {
            for ($m = 1; $m <= 12; $m++) {
                $dim = $c->daysInMonth($y, $m);
                foreach ([1, $dim] as $d) {
                    $jdn = $c->toJdn($y, $m, $d);
                    $this->assertSame([$y, $m, $d], $c->fromJdn($jdn), "AH {$y}-{$m}-{$d}");
                }
            }
        }
    }

    public function testName(): void
    {
        $this->assertSame('hijri-civil', HijriCivilCalendar::instance()->name());
    }

    public function testLocaleFamily(): void
    {
        $this->assertSame('hijri', HijriCivilCalendar::instance()->localeFamily());
    }

    public function testMonthsInYear(): void
    {
        $this->assertSame(12, HijriCivilCalendar::instance()->monthsInYear(1));
        $this->assertSame(12, HijriCivilCalendar::instance()->monthsInYear(1445));
    }
}
