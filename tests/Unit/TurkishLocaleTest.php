<?php

declare(strict_types=1);

namespace Eram\Daynum\Tests\Unit;

use Eram\Daynum\Calendar\Gregorian\GregorianView;
use Eram\Daynum\Calendar\Hijri\HijriUmmAlQuraView;
use Eram\Daynum\CivilDateTime;
use Eram\Daynum\Exception\InvalidArgumentException;
use Eram\Daynum\Locale\LocaleRegistry;
use Eram\Daynum\Locale\TurkishLocale;
use Eram\Daynum\Season;
use Eram\Daynum\WeekDay;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TurkishLocaleTest extends TestCase
{
    public function testRegistryResolvesTrAndTrTr(): void
    {
        $this->assertInstanceOf(TurkishLocale::class, LocaleRegistry::get('tr'));
        $this->assertSame('tr', LocaleRegistry::get('tr_TR')->tag());
    }

    public function testWeekStartsMondayWithSaturdaySundayWeekend(): void
    {
        $locale = new TurkishLocale();
        $this->assertSame(WeekDay::Monday, $locale->firstDayOfWeek());
        $this->assertSame([WeekDay::Saturday, WeekDay::Sunday], $locale->weekendDays());

        // 2026-04-08 is a Wednesday; the Turkish week began on Monday the 6th.
        $view = CivilDateTime::fromGregorian(2026, 4, 8)->gregorian()->withLocale('tr');
        $this->assertSame(6, $view->startOfWeek()->gregorian()->day());
    }

    public function testSeasonNames(): void
    {
        $locale = new TurkishLocale();
        $this->assertSame('İlkbahar', $locale->seasonName(Season::Spring));
        $this->assertSame('Yaz', $locale->seasonName(Season::Summer));
        $this->assertSame('Sonbahar', $locale->seasonName(Season::Autumn));
        $this->assertSame('Kış', $locale->seasonName(Season::Winter));
    }

    public function testRelativeTime(): void
    {
        $locale = new TurkishLocale();
        $this->assertSame('şimdi', $locale->relativeTimeNow());
        $this->assertSame('3 gün önce', $locale->relativeTime(3, 'day', false));
        $this->assertSame('3 gün sonra', $locale->relativeTime(3, 'day', true));
        $this->assertSame('1 yıl önce', $locale->relativeTime(1, 'year', false));
    }

    public function testRelativeTimeValidatesArguments(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Relative-time value must be non-negative; got -2.');
        (new TurkishLocale())->relativeTime(-2, 'hour', true);
    }

    public function testMeridiemAndOrdinal(): void
    {
        $locale = new TurkishLocale();
        $this->assertSame('ÖÖ', $locale->meridiem(false, true));
        $this->assertSame('ÖS', $locale->meridiem(true, true));
        $this->assertSame('öö', $locale->meridiem(false, false));
        $this->assertSame('ös', $locale->meridiem(true, false));
        $this->assertSame('', $locale->ordinalSuffix(3));
    }

    /** @return iterable<string, array{string, int}> */
    public static function meridiemProvider(): iterable
    {
        yield 'ÖS'          => ['3:00 ÖS', 15];
        yield 'ÖÖ'          => ['3:00 ÖÖ', 3];
        yield 'lowercase ös' => ['3:00 ös', 15];
    }

    #[DataProvider('meridiemProvider')]
    public function testParsesTurkishMeridiem(string $time, int $hour): void
    {
        $g = GregorianView::parseExact("2026-04-08 {$time}", 'Y-m-d g:i A', null, 'tr')->gregorian();
        $this->assertSame($hour, $g->hour());
    }

    /** Turkish case folding: dotted/dotless I and Ş/Ğ in either case. */
    public function testParsesNamesInAnyCase(): void
    {
        $expected = CivilDateTime::fromHijri(1447, 10, 20);
        foreach (['20 ŞEVVAL 1447', '20 şevval 1447', '20 Şevval 1447'] as $text) {
            $this->assertTrue(HijriUmmAlQuraView::parseExact($text, 'j F Y', null, 'tr')->equals($expected), $text);
        }

        $november = CivilDateTime::fromGregorian(2026, 11, 3);
        foreach (['3 KASIM 2026', '3 Kasım 2026', '3 kasim 2026', '3 KASİM 2026'] as $text) {
            $this->assertTrue(GregorianView::parseExact($text, 'j F Y', null, 'tr')->equals($november), $text);
        }
    }
}
