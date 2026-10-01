<?php

declare(strict_types=1);

namespace Eram\Daynum\Tests\Unit;

use Eram\Daynum\Calendar\Gregorian\GregorianView;
use Eram\Daynum\CivilDateTime;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * View behaviour shared by every calendar through AbstractCalendarView.
 */
final class CalendarViewTest extends TestCase
{
    public function testOfUsesTheGivenLocale(): void
    {
        $view = GregorianView::of(CivilDateTime::fromGregorian(2026, 1, 5), 'fa');
        $this->assertSame('ژانویهٔ', $view->format('F'));
    }

    public function testOfDefaultsToEnglish(): void
    {
        $view = GregorianView::of(CivilDateTime::fromGregorian(2026, 1, 5));
        $this->assertSame('January', $view->format('F'));
    }

    /**
     * Each timezone token on its own, and all of them together, must get the
     * DateTimeImmutable the formatter delegates to.
     */
    #[DataProvider('timezonePatternProvider')]
    public function testTimezoneTokensMatchDateTimeImmutable(string $pattern): void
    {
        $expected = (new \DateTimeImmutable('@1704067200'))
            ->setTimezone(new \DateTimeZone('Asia/Tehran'))
            ->format($pattern);

        $this->assertSame(
            $expected,
            CivilDateTime::fromTimestamp(1704067200, 'Asia/Tehran')->gregorian()->format($pattern),
        );
    }

    /** @return iterable<string, array{string}> */
    public static function timezonePatternProvider(): iterable
    {
        foreach (['U', 'O', 'P', 'p', 'Z', 'I', 'c', 'r', 'T'] as $token) {
            yield $token => [$token];
        }
        yield 'all together' => ['U O P p Z I c r T'];
    }

    public function testAddMonthsLandingOnTheLastMonth(): void
    {
        $g = CivilDateTime::fromGregorian(2026, 1, 31)->gregorian()->addMonths(11)->gregorian();
        $this->assertSame([2026, 12, 31], [$g->year(), $g->month(), $g->day()]);
    }

    public function testStartOfMonthIsDayOne(): void
    {
        $g = CivilDateTime::fromGregorian(2026, 5, 17, 9, 30)->gregorian()->startOfMonth()->gregorian();
        $this->assertSame([2026, 5, 1, 9, 30], [$g->year(), $g->month(), $g->day(), $g->hour(), $g->minute()]);
    }
}
