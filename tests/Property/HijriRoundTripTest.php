<?php

declare(strict_types=1);

namespace Daynum\Tests\Property;

use Daynum\Calendar\Hijri\HijriCivilCalendar;
use Daynum\Calendar\Hijri\HijriUmmAlQuraCalendar;
use Daynum\Calendar\Hijri\Table;
use Daynum\Instant;
use PHPUnit\Framework\TestCase;

/**
 * Round-trip and consistency properties for the two Hijri variants.
 *
 * Round-trip catches whole classes of algorithmic bug that hand-written
 * fixtures miss; running 10k seeded iterations gives wide coverage at low
 * cost. Failures are reproducible because the seed is fixed.
 */
final class HijriRoundTripTest extends TestCase
{
    private const ITERATIONS = 10_000;
    private const SEED = 0xDEAD;

    public function testGregorianRoundTripsThroughHijriCivil(): void
    {
        $rng = $this->seededRng();
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $y = $rng(1800, 2200);
            $m = $rng(1, 12);
            $d = $rng(1, 28);

            $instant = Instant::fromGregorian($y, $m, $d);
            $h = $instant->hijriCivil();
            $back = Instant::fromHijriCivil($h->year(), $h->month(), $h->day())->gregorian();

            $this->assertSame(
                [$y, $m, $d],
                [$back->year(), $back->month(), $back->day()],
                "Iteration {$i}: Gregorian→HijriCivil→Gregorian mismatch"
            );
        }
    }

    public function testHijriCivilRoundTripsThroughGregorian(): void
    {
        $rng = $this->seededRng();
        $c = HijriCivilCalendar::instance();
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $y = $rng(1, 2000);
            $m = $rng(1, 12);
            $d = $rng(1, $c->daysInMonth($y, $m));

            $instant = Instant::fromHijriCivil($y, $m, $d);
            $g = $instant->gregorian();
            $back = Instant::fromGregorian($g->year(), $g->month(), $g->day())->hijriCivil();

            $this->assertSame(
                [$y, $m, $d],
                [$back->year(), $back->month(), $back->day()],
                "Iteration {$i}: HijriCivil→Gregorian→HijriCivil mismatch"
            );
        }
    }

    public function testGregorianRoundTripsThroughUmmAlQuraInRange(): void
    {
        // 1900..2100 Gregorian sits comfortably inside the bundled UAQ
        // table for any plausible ICU build.
        $rng = $this->seededRng();
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $y = $rng(1900, 2100);
            $m = $rng(1, 12);
            $d = $rng(1, 28);

            $instant = Instant::fromGregorian($y, $m, $d);
            $h = $instant->hijri();
            $back = Instant::fromHijri($h->year(), $h->month(), $h->day())->gregorian();

            $this->assertSame(
                [$y, $m, $d],
                [$back->year(), $back->month(), $back->day()],
                "Iteration {$i}: Gregorian→UAQ→Gregorian mismatch"
            );
        }
    }

    public function testUmmAlQuraRoundTripsThroughGregorian(): void
    {
        $rng = $this->seededRng();
        $c = HijriUmmAlQuraCalendar::instance();
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $y = $rng(Table::MIN_YEAR, Table::MAX_YEAR);
            $m = $rng(1, 12);
            $d = $rng(1, $c->daysInMonth($y, $m));

            $instant = Instant::fromHijri($y, $m, $d);
            $g = $instant->gregorian();
            $back = Instant::fromGregorian($g->year(), $g->month(), $g->day())->hijri();

            $this->assertSame(
                [$y, $m, $d],
                [$back->year(), $back->month(), $back->day()],
                "Iteration {$i}: UAQ→Gregorian→UAQ mismatch"
            );
        }
    }

    public function testHijriCivilOrderingPreservesGregorianOrdering(): void
    {
        $rng = $this->seededRng();
        for ($i = 0; $i < 1000; $i++) {
            $y = $rng(1800, 2200);
            $m = $rng(1, 12);
            $d = $rng(1, 28);
            $offset = $rng(1, 5_000);

            $a = Instant::fromGregorian($y, $m, $d);
            $b = $a->gregorian()->addDays($offset);
            $this->assertTrue($a->lessThan($b));

            $ah = $a->hijriCivil();
            $bh = $b->hijriCivil();
            $aKey = sprintf('%05d-%02d-%02d', $ah->year(), $ah->month(), $ah->day());
            $bKey = sprintf('%05d-%02d-%02d', $bh->year(), $bh->month(), $bh->day());
            $this->assertLessThan($bKey, $aKey, 'Hijri civil ordering violated');
        }
    }

    public function testDayOfWeekIsViewIndependentAcrossAllFourCalendars(): void
    {
        $rng = $this->seededRng();
        for ($i = 0; $i < 1000; $i++) {
            $y = $rng(1900, 2100);
            $m = $rng(1, 12);
            $d = $rng(1, 28);

            $instant = Instant::fromGregorian($y, $m, $d);
            $g = $instant->gregorian()->dayOfWeek();
            $j = $instant->jalali()->dayOfWeek();
            $hc = $instant->hijriCivil()->dayOfWeek();
            $h = $instant->hijri()->dayOfWeek();
            $this->assertSame($g, $j);
            $this->assertSame($g, $hc);
            $this->assertSame($g, $h);
        }
    }

    /** @return \Closure(int,int):int */
    private function seededRng(): \Closure
    {
        mt_srand(self::SEED);
        return static fn (int $min, int $max): int => mt_rand($min, $max);
    }
}
