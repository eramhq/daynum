<?php

declare(strict_types=1);

namespace Daynum\Tests\EdgeCase;

use Daynum\Calendar\Gregorian\GregorianCalendar;
use Daynum\Instant;
use PHPUnit\Framework\TestCase;

final class GregorianLeapYearTest extends TestCase
{
    public function testCenturyRule(): void
    {
        $c = GregorianCalendar::instance();
        // Divisible by 100 but not 400 → not leap
        $this->assertFalse($c->isLeapYear(1700));
        $this->assertFalse($c->isLeapYear(1800));
        $this->assertFalse($c->isLeapYear(1900));
        $this->assertFalse($c->isLeapYear(2100));
        $this->assertFalse($c->isLeapYear(2200));
        $this->assertFalse($c->isLeapYear(2300));

        // Divisible by 400 → leap
        $this->assertTrue($c->isLeapYear(1600));
        $this->assertTrue($c->isLeapYear(2000));
        $this->assertTrue($c->isLeapYear(2400));
    }

    public function testFeb29OnlyAllowedInLeapYear(): void
    {
        // Feb 29 2024 is valid
        $leap = Instant::fromGregorian(2024, 2, 29);
        $this->assertSame(29, $leap->gregorian()->day());

        // Feb 29 2023 is not
        $this->expectException(\Daynum\Exception\InvalidDateException::class);
        Instant::fromGregorian(2023, 2, 29);
    }

    public function testYearZeroIsLeap(): void
    {
        // Year 0 in proleptic Gregorian is leap (divisible by 400)
        $this->assertTrue(GregorianCalendar::instance()->isLeapYear(0));
        $i = Instant::fromGregorian(0, 2, 29);
        $this->assertSame(0, $i->gregorian()->year());
        $this->assertSame(29, $i->gregorian()->day());
    }

    public function testAddingOneDayCrossesLeapBoundary(): void
    {
        // Feb 28 2024 + 1 day = Feb 29 2024
        $a = Instant::fromGregorian(2024, 2, 28);
        $this->assertSame('2024-02-29', $a->gregorian()->addDays(1)->gregorian()->format('Y-m-d'));

        // Feb 28 2025 + 1 day = Mar 1 2025
        $b = Instant::fromGregorian(2025, 2, 28);
        $this->assertSame('2025-03-01', $b->gregorian()->addDays(1)->gregorian()->format('Y-m-d'));
    }
}
