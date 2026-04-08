<?php

declare(strict_types=1);

namespace Daynum\Tests\Property;

use Daynum\Calendar\Jalali\JalaliCalendar;
use Daynum\Instant;
use PHPUnit\Framework\TestCase;

/**
 * Property: every Gregorian date within our fixture range must round-trip
 * through Jalali and back unchanged, and vice-versa.
 *
 * Round-trip catches entire classes of algorithmic bug that hand-written
 * fixtures miss: it's a mathematical invariant, not a point-wise check.
 *
 * Each test runs 10k pseudorandom iterations with a fixed seed so failures
 * are reproducible.
 */
final class RoundTripTest extends TestCase
{
    private const ITERATIONS = 10_000;
    private const SEED = 0xDA1E;

    public function testGregorianRoundTripThroughJalali(): void
    {
        $rng = $this->seededRng();
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $y = $rng(1900, 2100);
            $m = $rng(1, 12);
            $d = $rng(1, 28);

            $instant = Instant::fromGregorian($y, $m, $d);
            $j = $instant->jalali();
            $back = Instant::fromJalali($j->year(), $j->month(), $j->day())->gregorian();

            $this->assertSame(
                [$y, $m, $d],
                [$back->year(), $back->month(), $back->day()],
                "Iteration {$i}: Gregorian→Jalali→Gregorian mismatch"
            );
        }
    }

    public function testJalaliRoundTripThroughGregorian(): void
    {
        $rng = $this->seededRng();
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $y = $rng(1300, 1500);
            $m = $rng(1, 12);
            $d = $rng(1, JalaliCalendar::instance()->daysInMonth($y, $m));

            $instant = Instant::fromJalali($y, $m, $d);
            $g = $instant->gregorian();
            $back = Instant::fromGregorian($g->year(), $g->month(), $g->day())->jalali();

            $this->assertSame(
                [$y, $m, $d],
                [$back->year(), $back->month(), $back->day()],
                "Iteration {$i}: Jalali→Gregorian→Jalali mismatch"
            );
        }
    }

    public function testMonthBoundariesAreSelfConsistent(): void
    {
        $calendar = JalaliCalendar::instance();
        $rng = $this->seededRng();
        for ($i = 0; $i < 1000; $i++) {
            $y = $rng(JalaliCalendar::MIN_YEAR, JalaliCalendar::MAX_YEAR);
            $m = $rng(1, 12);
            $dim = $calendar->daysInMonth($y, $m);

            $jdnFirst = $calendar->toJdn($y, $m, 1);
            $jdnLast = $calendar->toJdn($y, $m, $dim);

            $this->assertSame($dim - 1, $jdnLast - $jdnFirst, 'month span mismatch');
            $this->assertSame([$y, $m, 1], $calendar->fromJdn($jdnFirst));
            $this->assertSame([$y, $m, $dim], $calendar->fromJdn($jdnLast));
        }
    }

    /**
     * Deterministic pseudorandom integer generator. Using `mt_rand` with a
     * fixed seed gives reproducible test runs across machines.
     *
     * @return \Closure(int,int):int
     */
    private function seededRng(): \Closure
    {
        mt_srand(self::SEED);
        return static fn (int $min, int $max): int => mt_rand($min, $max);
    }
}
