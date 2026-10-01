<?php

declare(strict_types=1);

namespace Eram\Daynum\Tests\Unit;

use Eram\Daynum\Calendar\Gregorian\GregorianView;
use Eram\Daynum\CivilDateTime;
use Eram\Daynum\Exception\InvalidArgumentException;
use Eram\Daynum\Locale\LocaleRegistry;
use Eram\Daynum\Locale\UrduLocale;
use Eram\Daynum\Season;
use Eram\Daynum\WeekDay;
use PHPUnit\Framework\TestCase;

final class UrduLocaleTest extends TestCase
{
    public function testRegistryResolvesUrAndUrPk(): void
    {
        $this->assertInstanceOf(UrduLocale::class, LocaleRegistry::get('ur'));
        $this->assertSame('ur', LocaleRegistry::get('ur-PK')->tag());
    }

    public function testWeekStartsSundayWithSaturdaySundayWeekend(): void
    {
        $locale = new UrduLocale();
        $this->assertSame(WeekDay::Sunday, $locale->firstDayOfWeek());
        $this->assertSame([WeekDay::Saturday, WeekDay::Sunday], $locale->weekendDays());

        // 2026-04-08 is a Wednesday; the Urdu week began on Sunday the 5th.
        $view = CivilDateTime::fromGregorian(2026, 4, 8)->gregorian()->withLocale('ur');
        $this->assertSame(5, $view->startOfWeek()->gregorian()->day());
    }

    public function testSeasonNames(): void
    {
        $locale = new UrduLocale();
        $this->assertSame('بہار', $locale->seasonName(Season::Spring));
        $this->assertSame('گرمی', $locale->seasonName(Season::Summer));
        $this->assertSame('خزاں', $locale->seasonName(Season::Autumn));
        $this->assertSame('سردی', $locale->seasonName(Season::Winter));
    }

    public function testRelativeTime(): void
    {
        $locale = new UrduLocale();
        $this->assertSame('اب', $locale->relativeTimeNow());
        $this->assertSame('3 دنوں پہلے', $locale->relativeTime(3, 'day', false));
        $this->assertSame('3 دنوں میں', $locale->relativeTime(3, 'day', true));
        // "1 hour" changes form with direction.
        $this->assertSame('1 گھنٹہ پہلے', $locale->relativeTime(1, 'hour', false));
        $this->assertSame('1 گھنٹے میں', $locale->relativeTime(1, 'hour', true));
    }

    public function testRelativeTimeValidatesArguments(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Unknown relative-time unit 'fortnight'");
        (new UrduLocale())->relativeTime(2, 'fortnight', false);
    }

    public function testMeridiemAndOrdinal(): void
    {
        $locale = new UrduLocale();
        $this->assertSame('PM', $locale->meridiem(true, true));
        $this->assertSame('am', $locale->meridiem(false, false));
        $this->assertSame('', $locale->ordinalSuffix(2));
    }

    public function testParsesMeridiem(): void
    {
        $g = GregorianView::parseExact('2026-04-08 3:00 PM', 'Y-m-d g:i A', null, 'ur')->gregorian();
        $this->assertSame(15, $g->hour());
    }
}
