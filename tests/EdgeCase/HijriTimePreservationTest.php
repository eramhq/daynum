<?php

declare(strict_types=1);

namespace Eram\Daynum\Tests\EdgeCase;

use Eram\Daynum\Instant;
use PHPUnit\Framework\TestCase;

/**
 * Calendar conversions must never touch the time-of-day or timezone label
 * carried on an Instant. The same invariant holds for the new Hijri views.
 */
final class HijriTimePreservationTest extends TestCase
{
    public function testTimeAndTzFlowThroughUaqView(): void
    {
        $i = Instant::fromGregorian(2024, 3, 11, 14, 30, 0, 'Asia/Riyadh');
        $this->assertSame(14, $i->hijri()->hour());
        $this->assertSame(30, $i->hijri()->minute());
        $this->assertSame(0, $i->hijri()->second());
        $this->assertSame('Asia/Riyadh', $i->tzLabel);
        // Format with H:i preserves the time.
        $this->assertSame('14:30:00', $i->hijri()->format('H:i:s'));
        $this->assertSame('Asia/Riyadh', $i->hijri()->format('e'));
    }

    public function testTimeAndTzFlowThroughCivilView(): void
    {
        $i = Instant::fromGregorian(2024, 3, 11, 14, 30, 45, 'UTC');
        $this->assertSame(14, $i->hijriCivil()->hour());
        $this->assertSame(30, $i->hijriCivil()->minute());
        $this->assertSame(45, $i->hijriCivil()->second());
        $this->assertSame('UTC', $i->tzLabel);
    }

    public function testFromHijriPreservesTimeAndTz(): void
    {
        $i = Instant::fromHijri(1445, 9, 1, 9, 15, 30, 'Asia/Riyadh');
        $this->assertSame(9 * 3600 + 15 * 60 + 30, $i->secondsOfDay);
        $this->assertSame('Asia/Riyadh', $i->tzLabel);
        // Round-trip through Gregorian preserves time/tz.
        $this->assertSame(9, $i->gregorian()->hour());
        $this->assertSame('Asia/Riyadh', $i->gregorian()->format('e'));
    }

    public function testArithmeticPreservesTime(): void
    {
        $i = Instant::fromGregorian(2024, 3, 11, 14, 30, 0, 'Asia/Riyadh');
        $next = $i->hijri()->addDays(7);
        $this->assertSame(14 * 3600 + 30 * 60, $next->secondsOfDay);
        $this->assertSame('Asia/Riyadh', $next->tzLabel);
    }
}
