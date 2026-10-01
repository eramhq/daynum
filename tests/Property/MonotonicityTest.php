<?php

declare(strict_types=1);

namespace Eram\Daynum\Tests\Property;

use Eram\Daynum\CivilDateTime;
use Eram\Daynum\Tests\Property\Support\Budget;
use PHPUnit\Framework\TestCase;

/**
 * Property: ordering is preserved across calendars.
 *
 * If Gregorian date A comes before Gregorian date B, then after converting
 * to Jalali the same must hold. Additionally, adding N days always produces
 * a value whose JDN is exactly N higher.
 */
final class MonotonicityTest extends TestCase
{
    private const ITERATIONS = 10_000;
    private const SEED = 0x1138;

    public function testGregorianOrderingPreservedInJalali(): void
    {
        mt_srand(self::SEED);
        $iterations = Budget::iterations(self::ITERATIONS);
        for ($i = 0; $i < $iterations; $i++) {
            $y = mt_rand(1900, 2100);
            $m = mt_rand(1, 12);
            $d = mt_rand(1, 28);
            $offset = mt_rand(1, 10_000);

            $a = CivilDateTime::fromGregorian($y, $m, $d);
            $b = $a->gregorian()->addDays($offset);

            $this->assertTrue($a->lessThan($b));

            $aj = $a->jalali();
            $bj = $b->jalali();
            $aKey = sprintf('%05d-%02d-%02d', $aj->year(), $aj->month(), $aj->day());
            $bKey = sprintf('%05d-%02d-%02d', $bj->year(), $bj->month(), $bj->day());
            $this->assertLessThan($bKey, $aKey, 'Jalali ordering violated');
        }
    }

    public function testAddingDaysStepsByOneJdn(): void
    {
        mt_srand(self::SEED + 1);
        $iterations = Budget::iterations(self::ITERATIONS);
        for ($i = 0; $i < $iterations; $i++) {
            $y = mt_rand(1, 3176);
            $m = mt_rand(1, 12);
            $d = mt_rand(1, 28);

            $dateTime = CivilDateTime::fromJalali($y, $m, $d);
            $next = $dateTime->jalali()->addDays(1);
            $this->assertSame($dateTime->jdn + 1, $next->jdn);
            $this->assertTrue($dateTime->lessThan($next));
        }
    }

    public function testSubtractingDaysIsInverseOfAdding(): void
    {
        mt_srand(self::SEED + 2);
        $iterations = Budget::iterations(self::ITERATIONS);
        for ($i = 0; $i < $iterations; $i++) {
            $y = mt_rand(1900, 2100);
            $m = mt_rand(1, 12);
            $d = mt_rand(1, 28);
            $n = mt_rand(1, 10_000);

            $a = CivilDateTime::fromGregorian($y, $m, $d);
            $b = $a->gregorian()->addDays($n);
            $c = $b->gregorian()->subDays($n);
            $this->assertSame($a->jdn, $c->jdn);
        }
    }
}
