<?php

declare(strict_types=1);

namespace Eram\Daynum\Tests\Unit;

use Eram\Daynum\Calendar\Gregorian\GregorianView;
use Eram\Daynum\Calendar\Hijri\HijriCivilView;
use Eram\Daynum\CivilDateTime;
use Eram\Daynum\Exception\InvalidArgumentException;
use Eram\Daynum\Locale\LocaleRegistry;
use Eram\Daynum\Locale\PashtoLocale;
use Eram\Daynum\Season;
use Eram\Daynum\WeekDay;
use PHPUnit\Framework\TestCase;

final class PashtoLocaleTest extends TestCase
{
    public function testRegistryResolvesPsAndPsAf(): void
    {
        $this->assertInstanceOf(PashtoLocale::class, LocaleRegistry::get('ps'));
        $this->assertSame('ps', LocaleRegistry::get('ps_AF')->tag());
    }

    public function testWeekStartsSaturdayWithThursdayFridayWeekend(): void
    {
        $locale = new PashtoLocale();
        $this->assertSame(WeekDay::Saturday, $locale->firstDayOfWeek());
        $this->assertSame([WeekDay::Thursday, WeekDay::Friday], $locale->weekendDays());

        // 2026-04-08 is a Wednesday; the Pashto week began on Saturday the 4th.
        $view = CivilDateTime::fromGregorian(2026, 4, 8)->gregorian()->withLocale('ps');
        $this->assertSame(4, $view->startOfWeek()->gregorian()->day());
        $this->assertTrue(CivilDateTime::fromGregorian(2026, 4, 9)->gregorian()->withLocale('ps')->isWeekend());
    }

    public function testSeasonNames(): void
    {
        $locale = new PashtoLocale();
        $this->assertSame('پسرلی', $locale->seasonName(Season::Spring));
        $this->assertSame('اوړی', $locale->seasonName(Season::Summer));
        $this->assertSame('منی', $locale->seasonName(Season::Autumn));
        $this->assertSame('ژمی', $locale->seasonName(Season::Winter));
    }

    public function testRelativeTime(): void
    {
        $locale = new PashtoLocale();
        $this->assertSame('اوس', $locale->relativeTimeNow());
        // The plural differs by direction; the singular does not.
        $this->assertSame('3 ورځې مخکې', $locale->relativeTime(3, 'day', false));
        $this->assertSame('په 3 ورځو کې', $locale->relativeTime(3, 'day', true));
        $this->assertSame('1 ورځ مخکې', $locale->relativeTime(1, 'day', false));
        $this->assertSame('په 1 ورځ کې', $locale->relativeTime(1, 'day', true));
    }

    public function testRelativeTimeValidatesArguments(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Relative-time value must be non-negative; got -1.');
        (new PashtoLocale())->relativeTime(-1, 'day', false);
    }

    public function testMeridiemAndOrdinal(): void
    {
        $locale = new PashtoLocale();
        $this->assertSame('غ.م.', $locale->meridiem(false, true));
        $this->assertSame('غ.و.', $locale->meridiem(true, false));
        $this->assertSame('', $locale->ordinalSuffix(1));
    }

    public function testParsesPashtoMeridiem(): void
    {
        $g = GregorianView::parseExact('2026-04-08 3:00 غ.و.', 'Y-m-d g:i A', null, 'ps')->gregorian();
        $this->assertSame(15, $g->hour());
    }

    public function testParsesAShortMonthNameHoldingADigit(): void
    {
        // ICU abbreviates Jumada al-Thani as "جماد ۲"; parseExact turns the
        // input's ۲ into 2 before matching, so the name must fold the same way.
        $h = HijriCivilView::parseExact('5 جماد ۲ 1447', 'j M Y', null, 'ps')->hijriCivil();
        $this->assertSame([1447, 6, 5], [$h->year(), $h->month(), $h->day()]);
    }
}
