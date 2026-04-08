<?php

declare(strict_types=1);

namespace Daynum\Tests\Unit;

use Daynum\Exception\InvalidDateException;
use Daynum\Instant;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

final class InstantTest extends TestCase
{
    public function testFromGregorianStoresJdnAndTime(): void
    {
        $i = Instant::fromGregorian(2026, 4, 8, 14, 30, 45, 'Asia/Tehran');

        $this->assertSame(2461139, $i->jdn);
        $this->assertSame(14 * 3600 + 30 * 60 + 45, $i->secondsOfDay);
        $this->assertSame('Asia/Tehran', $i->tzLabel);
    }

    public function testFromJalaliReturnsSameJdnAsEquivalentGregorian(): void
    {
        $gregorian = Instant::fromGregorian(2026, 4, 8);
        $jalali = Instant::fromJalali(1405, 1, 19);

        $this->assertSame($gregorian->jdn, $jalali->jdn);
    }

    public function testFromDateTimeRoundTripsThroughGregorian(): void
    {
        $dt = new DateTimeImmutable('2026-04-08 14:30:45', new DateTimeZone('UTC'));
        $i = Instant::fromDateTime($dt);

        $this->assertSame(2026, $i->gregorian()->year());
        $this->assertSame(4, $i->gregorian()->month());
        $this->assertSame(8, $i->gregorian()->day());
        $this->assertSame(14, $i->gregorian()->hour());
        $this->assertSame(30, $i->gregorian()->minute());
        $this->assertSame(45, $i->gregorian()->second());
        $this->assertSame('UTC', $i->tzLabel);
    }

    public function testToDateTimeImmutableRoundTripsComponents(): void
    {
        $i = Instant::fromGregorian(2026, 4, 8, 14, 30, 45, 'UTC');
        $dt = $i->toDateTimeImmutable();

        $this->assertSame('2026-04-08 14:30:45', $dt->format('Y-m-d H:i:s'));
        $this->assertSame('UTC', $dt->getTimezone()->getName());
    }

    public function testEqualsComparesJdnAndTimeOfDay(): void
    {
        $a = Instant::fromGregorian(2026, 4, 8, 14, 30);
        $b = Instant::fromGregorian(2026, 4, 8, 14, 30);
        $c = Instant::fromGregorian(2026, 4, 8, 14, 31);

        $this->assertTrue($a->equals($b));
        $this->assertFalse($a->equals($c));
    }

    public function testOrdering(): void
    {
        $earlier = Instant::fromGregorian(2026, 4, 8);
        $later = Instant::fromGregorian(2026, 4, 9);

        $this->assertTrue($earlier->lessThan($later));
        $this->assertTrue($later->greaterThan($earlier));
        $this->assertTrue($earlier->lessThanOrEqual($earlier));
        $this->assertTrue($earlier->greaterThanOrEqual($earlier));
        $this->assertFalse($earlier->greaterThan($later));
    }

    public function testDiffInDaysIsSigned(): void
    {
        $earlier = Instant::fromGregorian(2026, 4, 8);
        $later = Instant::fromGregorian(2026, 4, 10);

        $this->assertSame(2, $later->diffInDays($earlier));
        $this->assertSame(-2, $earlier->diffInDays($later));
    }

    public function testInvalidSecondsOfDayRejected(): void
    {
        $this->expectException(InvalidDateException::class);
        new Instant(0, 86400);
    }

    public function testInvalidTimeComponentsRejected(): void
    {
        $this->expectException(InvalidDateException::class);
        Instant::fromGregorian(2026, 4, 8, 24, 0, 0);
    }

    public function testInvalidGregorianDateRejected(): void
    {
        $this->expectException(InvalidDateException::class);
        Instant::fromGregorian(2026, 2, 30);
    }

    public function testInvalidJalaliMonthRejected(): void
    {
        $this->expectException(InvalidDateException::class);
        Instant::fromJalali(1405, 13, 1);
    }

    public function testJalaliOutOfRangeRejected(): void
    {
        $this->expectException(InvalidDateException::class);
        Instant::fromJalali(4000, 1, 1);
    }
}
