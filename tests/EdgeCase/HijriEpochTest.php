<?php

declare(strict_types=1);

namespace Eram\Daynum\Tests\EdgeCase;

use Eram\Daynum\Calendar\Hijri\HijriCivilCalendar;
use Eram\Daynum\Instant;
use PHPUnit\Framework\TestCase;

/**
 * Properties of the Hijri epoch (AH 1 Muharram 1) and the first year.
 *
 * These are static facts from the Reingold–Dershowitz reference; any
 * regression here means the `EPOCH` constant or the leap-year formula
 * has drifted.
 */
final class HijriEpochTest extends TestCase
{
    public function testAh1MuharramIsJdn1948440(): void
    {
        $this->assertSame(1948440, HijriCivilCalendar::EPOCH);
        $this->assertSame(
            1948440,
            HijriCivilCalendar::instance()->toJdn(1, 1, 1)
        );
    }

    public function testAh1MuharramFallsOnAFriday(): void
    {
        // JDN 0 is a Monday; Daynum's w = (jdn + 1) mod 7 (Sun=0..Sat=6).
        // For JDN 1948440, w = 1948441 mod 7 = 5 → Friday.
        $instant = Instant::fromHijriCivil(1, 1, 1);
        $this->assertSame(5, $instant->hijriCivil()->dayOfWeek());
        $this->assertSame('Friday', $instant->hijriCivil()->format('l'));
    }

    public function testMuharramHas30Days(): void
    {
        $this->assertSame(
            30,
            HijriCivilCalendar::instance()->daysInMonth(1, 1)
        );
    }

    public function testAh1IsCommonYearOf354Days(): void
    {
        $c = HijriCivilCalendar::instance();
        $this->assertFalse($c->isLeapYear(1));
        $total = 0;
        for ($m = 1; $m <= 12; $m++) {
            $total += $c->daysInMonth(1, $m);
        }
        $this->assertSame(354, $total);
    }

    public function testAh2StartsExactly354DaysAfterAh1(): void
    {
        $c = HijriCivilCalendar::instance();
        $this->assertSame(1948440 + 354, $c->toJdn(2, 1, 1));
    }

    public function testEpochRoundTripsBothDirections(): void
    {
        $c = HijriCivilCalendar::instance();
        $this->assertSame([1, 1, 1], $c->fromJdn(1948440));
        $this->assertSame(1948440, $c->toJdn(1, 1, 1));
    }
}
