<?php

declare(strict_types=1);

namespace Daynum\Tests\Property;

use Daynum\Calendar;
use Daynum\Calendar\Gregorian\GregorianCalendar;
use Daynum\Calendar\Hijri\HijriCivilCalendar;
use Daynum\Calendar\Hijri\HijriUmmAlQuraCalendar;
use Daynum\Calendar\Hijri\Table;
use Daynum\Calendar\Jalali\JalaliCalendar;
use PHPUnit\Framework\TestCase;

/**
 * Property: for every calendar, `dayOfYear(y, m, d)` must equal
 * `toJdn(y, m, d) - toJdn(y, 1, 1) + 1`. The JDN round-trip formula
 * is an independent oracle — if any closed-form implementation is
 * wrong, the two answers will diverge.
 */
final class DayOfYearAgainstJdnRoundTripTest extends TestCase
{
    private const SAMPLES_PER_CALENDAR = 50;
    private const SEED = 0xD074;

    /**
     * @dataProvider calendarsWithYearRanges
     */
    public function testDayOfYearMatchesJdnOracle(Calendar $c, int $minYear, int $maxYear): void
    {
        $rng = $this->seededRng();
        for ($i = 0; $i < self::SAMPLES_PER_CALENDAR; $i++) {
            $y = $rng($minYear, $maxYear);
            $m = $rng(1, 12);
            $d = $rng(1, $c->daysInMonth($y, $m));

            $expected = $c->toJdn($y, $m, $d) - $c->toJdn($y, 1, 1) + 1;
            $this->assertSame(
                $expected,
                $c->dayOfYear($y, $m, $d),
                sprintf('%s dayOfYear(%d, %d, %d)', $c->name(), $y, $m, $d)
            );
        }
    }

    /**
     * @return iterable<string, array{Calendar,int,int}>
     */
    public static function calendarsWithYearRanges(): iterable
    {
        yield 'gregorian'      => [GregorianCalendar::instance(), GregorianCalendar::MIN_YEAR, GregorianCalendar::MAX_YEAR];
        yield 'jalali'         => [JalaliCalendar::instance(), JalaliCalendar::MIN_YEAR, JalaliCalendar::MAX_YEAR];
        yield 'hijri-civil'    => [HijriCivilCalendar::instance(), HijriCivilCalendar::MIN_YEAR, HijriCivilCalendar::MAX_YEAR];
        yield 'hijri-umalqura' => [HijriUmmAlQuraCalendar::instance(), Table::MIN_YEAR, Table::MAX_YEAR];
    }

    /** @return \Closure(int,int):int */
    private function seededRng(): \Closure
    {
        mt_srand(self::SEED);
        return static fn (int $min, int $max): int => mt_rand($min, $max);
    }
}
