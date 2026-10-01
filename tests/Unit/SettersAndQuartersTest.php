<?php

declare(strict_types=1);

namespace Eram\Daynum\Tests\Unit;

use Eram\Daynum\CivilDateTime;
use Eram\Daynum\Exception\InvalidDateException;
use Eram\Daynum\Season;
use PHPUnit\Framework\TestCase;

final class SettersAndQuartersTest extends TestCase
{
    // ─── with() ─────────────────────────────────────────────────────

    public function testWithReplacesOnlyGivenParts(): void
    {
        $d = CivilDateTime::fromJalali(1405, 1, 19, 14, 30, 45, 'Asia/Tehran');

        $this->assertSame('1405/01/01 14:30:45', $d->jalali()->with(day: 1)->jalali()->format('Y/m/d H:i:s'));
        $this->assertSame('1405/07/01 14:30:45', $d->jalali()->with(month: 7, day: 1)->jalali()->format('Y/m/d H:i:s'));
        $this->assertSame('1403/01/19 09:00:00', $d->jalali()->with(year: 1403, hour: 9, minute: 0, second: 0)->jalali()->format('Y/m/d H:i:s'));
        $this->assertSame('Asia/Tehran', $d->jalali()->with(day: 2)->tzLabel);
        $this->assertTrue($d->jalali()->with()->equals($d));
    }

    public function testWithWorksInTheViewCalendar(): void
    {
        $d = CivilDateTime::fromGregorian(2026, 4, 8);
        $this->assertSame('2026-12-08', $d->gregorian()->with(month: 12)->gregorian()->format('Y-m-d'));
        $this->assertSame('1447/09/01', $d->hijri()->with(month: 9, day: 1)->hijri()->format('Y/m/d'));
    }

    public function testWithDoesNotClamp(): void
    {
        // Mehr has 30 days.
        $this->expectException(InvalidDateException::class);
        CivilDateTime::fromJalali(1405, 6, 31)->jalali()->with(month: 7);
    }

    public function testWithRejectsInvalidTime(): void
    {
        $this->expectException(InvalidDateException::class);
        CivilDateTime::fromGregorian(2026, 4, 8)->gregorian()->with(hour: 24);
    }

    // ─── Quarters ───────────────────────────────────────────────────

    public function testQuarterBoundaries(): void
    {
        $expected = [1 => 1, 2 => 1, 3 => 1, 4 => 2, 6 => 2, 7 => 3, 9 => 3, 10 => 4, 12 => 4];
        foreach ($expected as $month => $quarter) {
            $this->assertSame($quarter, CivilDateTime::fromGregorian(2026, $month, 15)->gregorian()->quarter(), "month {$month}");
        }
    }

    public function testStartAndEndOfQuarter(): void
    {
        $d = CivilDateTime::fromGregorian(2026, 5, 20, 10, 0, 0);
        $this->assertSame('2026-04-01 10:00', $d->gregorian()->startOfQuarter()->gregorian()->format('Y-m-d H:i'));
        $this->assertSame('2026-06-30 10:00', $d->gregorian()->endOfQuarter()->gregorian()->format('Y-m-d H:i'));

        // Jalali leap year: Esfand 1403 has 30 days.
        $esfand = CivilDateTime::fromJalali(1403, 11, 5);
        $this->assertSame('1403/10/01', $esfand->jalali()->startOfQuarter()->jalali()->format('Y/m/d'));
        $this->assertSame('1403/12/30', $esfand->jalali()->endOfQuarter()->jalali()->format('Y/m/d'));
    }

    // ─── Seasons ────────────────────────────────────────────────────

    public function testJalaliSeasonsFollowQuarters(): void
    {
        $seasons = [1 => Season::Spring, 3 => Season::Spring, 4 => Season::Summer, 7 => Season::Autumn, 9 => Season::Autumn, 10 => Season::Winter, 12 => Season::Winter];
        foreach ($seasons as $month => $season) {
            $this->assertSame($season, CivilDateTime::fromJalali(1405, $month, 1)->jalali()->season(), "month {$month}");
        }
    }

    public function testSeasonNames(): void
    {
        $autumn = CivilDateTime::fromJalali(1405, 8, 1)->jalali();
        $this->assertSame('Autumn', $autumn->seasonName());
        $this->assertSame('پاییز', $autumn->withLocale('fa')->seasonName());
        $this->assertSame('خزان', $autumn->withLocale('fa-AF')->seasonName());
        $this->assertSame('الخريف', $autumn->withLocale('ar')->seasonName());
        $this->assertSame('بهار', CivilDateTime::fromJalali(1405, 1, 1)->jalali()->withLocale('fa')->seasonName());
    }
}
