<?php

declare(strict_types=1);

namespace Eram\Daynum\Tests\Property;

use Eram\Daynum\Instant;
use PHPUnit\Framework\TestCase;

/**
 * Property: the day-of-week is a function of the JDN alone. Asking for it via
 * the Gregorian view must yield the same answer as asking via the Jalali view,
 * and asking repeatedly must be deterministic.
 */
final class DayOfWeekConsistencyTest extends TestCase
{
    private const ITERATIONS = 10_000;
    private const SEED = 0xD7E4;

    public function testDayOfWeekIsViewIndependent(): void
    {
        mt_srand(self::SEED);
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $y = mt_rand(1900, 2100);
            $m = mt_rand(1, 12);
            $d = mt_rand(1, 28);

            $instant = Instant::fromGregorian($y, $m, $d);
            $this->assertSame(
                $instant->gregorian()->dayOfWeek(),
                $instant->jalali()->dayOfWeek(),
                "Iteration {$i}: Gregorian and Jalali views disagree on day-of-week"
            );
            $this->assertSame(
                $instant->gregorian()->dayOfWeekIso(),
                $instant->jalali()->dayOfWeekIso()
            );
        }
    }

    public function testIsoAndPhpDowConvention(): void
    {
        mt_srand(self::SEED + 1);
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $y = mt_rand(1900, 2100);
            $m = mt_rand(1, 12);
            $d = mt_rand(1, 28);

            $instant = Instant::fromGregorian($y, $m, $d);
            $php = $instant->gregorian()->dayOfWeek();   // 0..6, Sun=0
            $iso = $instant->gregorian()->dayOfWeekIso(); // 1..7, Mon=1

            // Sun: php=0 → iso=7; Mon..Sat: php=1..6 → iso=1..6.
            $expected = $php === 0 ? 7 : $php;
            $this->assertSame($expected, $iso);
            $this->assertGreaterThanOrEqual(0, $php);
            $this->assertLessThanOrEqual(6, $php);
            $this->assertGreaterThanOrEqual(1, $iso);
            $this->assertLessThanOrEqual(7, $iso);
        }
    }

    public function testConsecutiveDaysAdvanceDayOfWeekByOne(): void
    {
        mt_srand(self::SEED + 2);
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $y = mt_rand(1900, 2100);
            $m = mt_rand(1, 12);
            $d = mt_rand(1, 27);

            $a = Instant::fromGregorian($y, $m, $d);
            $b = $a->gregorian()->addDays(1);
            $this->assertSame(
                ($a->gregorian()->dayOfWeek() + 1) % 7,
                $b->gregorian()->dayOfWeek()
            );
        }
    }
}
