<?php

declare(strict_types=1);

namespace Daynum\Tests\EdgeCase;

use Daynum\Calendar\Gregorian\GregorianCalendar;
use Daynum\Calendar\Jalali\JalaliCalendar;
use Daynum\Exception\InvalidDateException;
use Daynum\Instant;
use PHPUnit\Framework\TestCase;

final class BoundaryTest extends TestCase
{
    public function testJalaliMinimumYearIsValid(): void
    {
        $i = Instant::fromJalali(JalaliCalendar::MIN_YEAR, 1, 1);
        $this->assertSame(JalaliCalendar::MIN_YEAR, $i->jalali()->year());
        $this->assertSame(1, $i->jalali()->month());
        $this->assertSame(1, $i->jalali()->day());
    }

    public function testJalaliMaximumYearIsValid(): void
    {
        $i = Instant::fromJalali(JalaliCalendar::MAX_YEAR, 1, 1);
        $this->assertSame(JalaliCalendar::MAX_YEAR, $i->jalali()->year());
    }

    public function testJalaliBelowMinimumThrows(): void
    {
        $this->expectException(InvalidDateException::class);
        Instant::fromJalali(JalaliCalendar::MIN_YEAR - 1, 1, 1);
    }

    public function testJalaliAboveMaximumThrows(): void
    {
        $this->expectException(InvalidDateException::class);
        Instant::fromJalali(JalaliCalendar::MAX_YEAR + 1, 1, 1);
    }

    public function testGregorianRangeEndpoints(): void
    {
        $calendar = GregorianCalendar::instance();
        [$minJdn, $maxJdn] = $calendar->supportedRange();

        $this->assertSame([GregorianCalendar::MIN_YEAR, 1, 1], $calendar->fromJdn($minJdn));
        $this->assertSame([GregorianCalendar::MAX_YEAR, 12, 31], $calendar->fromJdn($maxJdn));
    }

    public function testJalaliRangeEndpoints(): void
    {
        $calendar = JalaliCalendar::instance();
        [$minJdn, $maxJdn] = $calendar->supportedRange();

        $this->assertSame([JalaliCalendar::MIN_YEAR, 1, 1], $calendar->fromJdn($minJdn));
        $minYear = JalaliCalendar::MAX_YEAR;
        $lastDay = $calendar->daysInMonth($minYear, 12);
        $this->assertSame([$minYear, 12, $lastDay], $calendar->fromJdn($maxJdn));
    }
}
