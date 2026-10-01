<?php

declare(strict_types=1);

namespace Eram\Daynum\Tests\Unit;

use Eram\Daynum\Calendar\Gregorian\GregorianCalendar;
use Eram\Daynum\Calendar\Hijri\HijriUmmAlQuraCalendar;
use Eram\Daynum\CivilDateTime;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Unit thresholds of diffForHumans() and the date-only month/year diffs it
 * builds on, checked at the exact boundary in both directions.
 */
final class RelativeTimeTest extends TestCase
{
    /**
     * Offsets either side of 2026-04-08 12:00:00.
     *
     * @return iterable<string, array{int, string, string}>
     */
    public static function unitBoundaryProvider(): iterable
    {
        yield '1 hour'             => [3600, 'in 1 hour', '1 hour ago'];
        yield '59m 59s'            => [3599, 'in 59 minutes', '59 minutes ago'];
        yield '1 day'              => [86400, 'in 1 day', '1 day ago'];
        yield '7 days'             => [7 * 86400, 'in 1 week', '1 week ago'];
        yield '7 days less 1s'     => [7 * 86400 - 1, 'in 6 days', '6 days ago'];
        yield '7 days plus 6s'     => [7 * 86400 + 6, 'in 1 week', '1 week ago'];
        yield '14 days less 1s'    => [14 * 86400 - 1, 'in 1 week', '1 week ago'];
        yield '28 days less 1s'    => [28 * 86400 - 1, 'in 3 weeks', '3 weeks ago'];
    }

    #[DataProvider('unitBoundaryProvider')]
    public function testUnitBoundariesInBothDirections(int $offset, string $future, string $past): void
    {
        $now = CivilDateTime::fromGregorian(2026, 4, 8, 12, 0, 0, 'UTC');

        $this->assertSame($future, $now->addSeconds($offset)->gregorian()->diffForHumans($now));
        $this->assertSame($past, $now->subSeconds($offset)->gregorian()->diffForHumans($now));
    }

    public function testExactly28DaysIsOneMonthWhenTheMonthHas28Days(): void
    {
        $feb1 = CivilDateTime::fromGregorian(2026, 2, 1, 12);
        $mar1 = CivilDateTime::fromGregorian(2026, 3, 1, 12);

        $this->assertSame('in 1 month', $mar1->gregorian()->diffForHumans($feb1));
        $this->assertSame('1 month ago', $feb1->gregorian()->diffForHumans($mar1));
    }

    public function testExactlyOneMonthToTheSecond(): void
    {
        $from = CivilDateTime::fromGregorian(2026, 3, 1, 12);
        $to = CivilDateTime::fromGregorian(2026, 4, 1, 12);

        $this->assertSame('in 1 month', $to->gregorian()->diffForHumans($from));
        $this->assertSame('1 month ago', $from->gregorian()->diffForHumans($to));
        $this->assertSame('in 4 weeks', $to->subSeconds(1)->gregorian()->diffForHumans($from));
        $this->assertSame('4 weeks ago', $from->addSeconds(1)->gregorian()->diffForHumans($to));
    }

    public function testExactlyOneYearToTheSecond(): void
    {
        $from = CivilDateTime::fromGregorian(2025, 4, 8, 12);
        $to = CivilDateTime::fromGregorian(2026, 4, 8, 12);

        $this->assertSame('in 1 year', $to->gregorian()->diffForHumans($from));
        $this->assertSame('1 year ago', $from->gregorian()->diffForHumans($to));
        $this->assertSame('in 11 months', $to->subSeconds(1)->gregorian()->diffForHumans($from));
        $this->assertSame('11 months ago', $from->addSeconds(1)->gregorian()->diffForHumans($to));
    }

    // ─── diffInMonths / diffInYears inside the same month or year ───

    public function testDiffInMonthsWithinOneMonthIsZero(): void
    {
        $early = CivilDateTime::fromGregorian(2026, 4, 5);
        $late = CivilDateTime::fromGregorian(2026, 4, 10);

        $this->assertSame(0, $early->gregorian()->diffInMonths($late));
        $this->assertSame(0, $late->gregorian()->diffInMonths($early));
    }

    public function testDiffInYearsWithinOneYearIsZero(): void
    {
        $march = CivilDateTime::fromGregorian(2026, 3, 1);
        $april = CivilDateTime::fromGregorian(2026, 4, 1);

        $this->assertSame(0, $march->gregorian()->diffInYears($april));
        $this->assertSame(0, $april->gregorian()->diffInYears($march));
    }

    public function testDiffInYearsBackwardDayNotReached(): void
    {
        // Same month, later day: not yet a whole year backwards.
        $a = CivilDateTime::fromGregorian(2025, 3, 15);
        $b = CivilDateTime::fromGregorian(2026, 3, 10);
        $this->assertSame(0, $a->gregorian()->diffInYears($b));
        $this->assertSame(-1, $a->gregorian()->diffInYears($b->addDays(5)));

        // Later month: not yet a whole year backwards.
        $c = CivilDateTime::fromGregorian(2025, 4, 1);
        $this->assertSame(0, $c->gregorian()->diffInYears(CivilDateTime::fromGregorian(2026, 3, 1)));
    }

    // ─── isInSupportedRange ─────────────────────────────────────────

    public function testIsInSupportedRangeIncludesBothEnds(): void
    {
        [$min, $max] = HijriUmmAlQuraCalendar::instance()->supportedRange();
        $this->assertTrue((new CivilDateTime($min))->hijri()->isInSupportedRange());
        $this->assertTrue((new CivilDateTime($max))->hijri()->isInSupportedRange());
        $this->assertFalse((new CivilDateTime($min - 1))->hijri()->isInSupportedRange());
        $this->assertFalse((new CivilDateTime($max + 1))->hijri()->isInSupportedRange());

        [$min, $max] = GregorianCalendar::instance()->supportedRange();
        $this->assertTrue((new CivilDateTime($min))->gregorian()->isInSupportedRange());
        $this->assertTrue((new CivilDateTime($max))->gregorian()->isInSupportedRange());
    }
}
