<?php

declare(strict_types=1);

namespace Eram\Daynum\Tests\EdgeCase;

use Eram\Daynum\Calendar\Gregorian\GregorianCalendar;
use Eram\Daynum\Instant;
use PHPUnit\Framework\TestCase;

/**
 * Daynum uses proleptic Gregorian: year 0 exists (= 1 BCE), negative years are
 * permitted, and there is no Julian cutover around October 1582. These are the
 * cases that trip up calendar libraries that use a historical cutover.
 */
final class ProlepticGregorianTest extends TestCase
{
    /**
     * @dataProvider negativeYears
     */
    public function testNegativeYearRoundTrip(int $year, int $month, int $day): void
    {
        $calendar = GregorianCalendar::instance();
        $jdn = $calendar->toJdn($year, $month, $day);
        $this->assertSame([$year, $month, $day], $calendar->fromJdn($jdn));
    }

    /**
     * @return iterable<string, array{int,int,int}>
     */
    public static function negativeYears(): iterable
    {
        yield 'year 0 Jan 1'   => [0, 1, 1];
        yield 'year 0 Dec 31'  => [0, 12, 31];
        yield 'year -1 Jan 1'  => [-1, 1, 1];
        yield 'year -1 Dec 31' => [-1, 12, 31];
        yield 'year -100'      => [-100, 6, 15];
        yield 'year -500'      => [-500, 3, 1];
        yield 'Caesar'         => [-44, 3, 15];
    }

    public function testNoOctober1582Cutover(): void
    {
        // In the historical Julian→Gregorian cutover, Oct 5–14, 1582 didn't
        // exist. In proleptic Gregorian they do, and every consecutive pair of
        // days is exactly 1 JDN apart.
        $d = Instant::fromGregorian(1582, 10, 4);
        $this->assertSame('1582-10-05', $d->gregorian()->addDays(1)->gregorian()->format('Y-m-d'));
        $this->assertSame('1582-10-10', $d->gregorian()->addDays(6)->gregorian()->format('Y-m-d'));
        $this->assertSame('1582-10-15', $d->gregorian()->addDays(11)->gregorian()->format('Y-m-d'));
    }

    public function testYearBeforeOneBcIsNegative(): void
    {
        // Going back 1 day from Jan 1 year 0 should give Dec 31 year -1.
        $jan1Year0 = Instant::fromGregorian(0, 1, 1);
        $prev = $jan1Year0->gregorian()->subDays(1);
        $this->assertSame(-1, $prev->gregorian()->year());
        $this->assertSame(12, $prev->gregorian()->month());
        $this->assertSame(31, $prev->gregorian()->day());
    }
}
