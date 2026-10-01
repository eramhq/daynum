<?php

declare(strict_types=1);

namespace Eram\Daynum\Tests\Unit;

use Eram\Daynum\Calendar\AbstractCalendarView;
use Eram\Daynum\Calendar\Gregorian\GregorianView;
use Eram\Daynum\Calendar\Hijri\HijriCivilView;
use Eram\Daynum\Calendar\Hijri\HijriUmmAlQuraView;
use Eram\Daynum\Calendar\Jalali\JalaliView;
use Eram\Daynum\CivilDateTime;
use Eram\Daynum\Exception\InvalidArgumentException;
use Eram\Daynum\Exception\ParseException;
use Eram\Daynum\Locale\EnglishLocale;
use Eram\Daynum\Locale\LocaleRegistry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Parsing month and weekday names (`F M l D`) with `parseExact(..., $locale)`.
 */
final class ParseNamesTest extends TestCase
{
    /**
     * @return iterable<string, array{string, class-string<AbstractCalendarView>, string, int}>
     */
    public static function localeCalendarProvider(): iterable
    {
        $views = [
            'gregorian'  => [GregorianView::class, 2026],
            'jalali'     => [JalaliView::class, 1405],
            'hijri'      => [HijriUmmAlQuraView::class, 1447],
            'hijriCivil' => [HijriCivilView::class, 1447],
        ];
        foreach (['en', 'fa', 'fa-AF', 'ar'] as $locale) {
            foreach ($views as $method => [$class, $year]) {
                if ($locale === 'ar' && $method === 'jalali') {
                    continue; // Arabic ships no Jalali month names.
                }
                yield "{$locale} {$method}" => [$locale, $class, $method, $year];
            }
        }
    }

    /**
     * format('l j F Y') → parseExact round-trips for every month, and the
     * short `D j M Y` form does too.
     *
     * @param class-string<AbstractCalendarView> $viewClass
     */
    #[DataProvider('localeCalendarProvider')]
    public function testFormatThenParseRoundTripsEveryMonth(string $locale, string $viewClass, string $method, int $year): void
    {
        foreach (['l j F Y', 'D j M Y', 'l، j F Y'] as $pattern) {
            for ($month = 1; $month <= 12; $month++) {
                foreach ([1, 15, 28] as $day) {
                    $original = match ($method) {
                        'gregorian'  => CivilDateTime::fromGregorian($year, $month, $day),
                        'jalali'     => CivilDateTime::fromJalali($year, $month, $day),
                        'hijri'      => CivilDateTime::fromHijri($year, $month, $day),
                        'hijriCivil' => CivilDateTime::fromHijriCivil($year, $month, $day),
                        default      => throw new \LogicException("Unknown calendar: {$method}"),
                    };
                    foreach (['latn', 'persian', 'arab'] as $digits) {
                        $text = $original->{$method}()->withLocale($locale)->withDigits($digits)->format($pattern);
                        $parsed = $viewClass::parseExact($text, $pattern, null, $locale);
                        $this->assertTrue($parsed->equals($original), "{$locale} {$method} {$pattern}: {$text}");
                    }
                }
            }
        }
    }

    public function testEnglishNamesAreCaseInsensitive(): void
    {
        $expected = CivilDateTime::fromGregorian(2026, 4, 8);
        $this->assertTrue(GregorianView::parseExact('WEDNESDAY 8 april 2026', 'l j F Y')->equals($expected));
        $this->assertTrue(JalaliView::parseExact('19 FARVARDIN 1405', 'j F Y')->equals($expected));
    }

    public function testFAndMAcceptBothLongAndShortNames(): void
    {
        $expected = CivilDateTime::fromGregorian(2026, 9, 3);
        $this->assertTrue(GregorianView::parseExact('3 Sep 2026', 'j F Y')->equals($expected));
        $this->assertTrue(GregorianView::parseExact('3 September 2026', 'j M Y')->equals($expected));
        $this->assertTrue(GregorianView::parseExact('Thu 3 Sep 2026', 'l j M Y')->equals($expected));
    }

    public function testDefaultParseLocaleIsEnglish(): void
    {
        $this->assertNull(JalaliView::tryParseExact('19 فروردین 1405', 'j F Y'));
        $this->assertNotNull(JalaliView::tryParseExact('19 فروردین 1405', 'j F Y', null, 'fa'));
    }

    public function testArabicKeyboardLettersMatchPersianNames(): void
    {
        // ي (U+064A) and ك (U+0643) typed in place of ی and ک.
        $expected = CivilDateTime::fromJalali(1405, 2, 1);
        $this->assertTrue(JalaliView::parseExact('1 ارديبهشت 1405', 'j F Y', null, 'fa')->equals($expected));
        $this->assertTrue(JalaliView::parseExact('يكشنبه 6 ارديبهشت 1405', 'l j F Y', null, 'fa')->equals(CivilDateTime::fromJalali(1405, 2, 6)));
    }

    public function testAlefMaksuraMatchesFarsiYeh(): void
    {
        // Persian جمادی‌الاول typed with Arabic ى and no ZWNJ.
        $this->assertTrue(HijriCivilView::parseExact('1 جمادىالاول 1447', 'j F Y', null, 'fa')->equals(CivilDateTime::fromHijriCivil(1447, 5, 1)));
    }

    public function testZwnjAndEzafeAreOptional(): void
    {
        // سه‌شنبه written without its ZWNJ; ژانویهٔ written without the ezafe hamza.
        $tuesday = CivilDateTime::fromGregorian(2026, 1, 6);
        $this->assertTrue(GregorianView::parseExact('سهشنبه 6 ژانویه 2026', 'l j F Y', null, 'fa')->equals($tuesday));
        // ...and the ezafe is still accepted when present.
        $this->assertTrue(GregorianView::parseExact('6 ژانویهٔ 2026', 'j F Y', null, 'fa')->equals($tuesday));
        $this->assertTrue(HijriCivilView::parseExact('1 ربیعالاول 1447', 'j F Y', null, 'fa')->equals(CivilDateTime::fromHijriCivil(1447, 3, 1)));
    }

    public function testLongestNameWins(): void
    {
        // "June" must not be read as "Jun" + trailing "e".
        $this->assertTrue(GregorianView::parseExact('June 5, 2026', 'F j, Y')->equals(CivilDateTime::fromGregorian(2026, 6, 5)));
        // Persian: پنجشنبه (Thursday) contains شنبه (Saturday).
        $this->assertTrue(GregorianView::parseExact('پنجشنبه 9 آوریل 2026', 'l j F Y', null, 'fa')->equals(CivilDateTime::fromGregorian(2026, 4, 9)));
    }

    public function testDariMonthNames(): void
    {
        $this->assertTrue(JalaliView::parseExact('3 سنبله 1405', 'j F Y', null, 'fa-AF')->equals(CivilDateTime::fromJalali(1405, 6, 3)));
        $this->assertTrue(GregorianView::parseExact('3 جولای 2026', 'j F Y', null, 'fa_af')->equals(CivilDateTime::fromGregorian(2026, 7, 3)));
    }

    public function testWeekdayMustMatchDate(): void
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('weekday "Monday" does not match the date, which is a Wednesday');
        GregorianView::parseExact('Monday 8 April 2026', 'l j F Y');
    }

    public function testConflictingMonthTokensThrow(): void
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('conflicting values for month');
        GregorianView::parseExact('April 2026-05-08', 'F Y-m-d');
    }

    public function testAgreeingMonthTokensAreAccepted(): void
    {
        $this->assertTrue(GregorianView::parseExact('April 2026-04-08', 'F Y-m-d')->equals(CivilDateTime::fromGregorian(2026, 4, 8)));
    }

    public function testUnknownMonthNameThrows(): void
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('expected a month name for "F" at position 2');
        GregorianView::parseExact('8 Aprl 2026', 'j F Y');
    }

    public function testPersianNameFollowedByPersianLetterIsRejected(): void
    {
        // "دیروز" starts with "دی" (month 10) but is a different word.
        $this->assertNull(JalaliView::tryParseExact('5 دیروز 1405', 'j F Y', null, 'fa'));
        $this->assertNotNull(JalaliView::tryParseExact('5 دی 1405', 'j F Y', null, 'fa'));
    }

    public function testArabicLocaleHasNoJalaliMonths(): void
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('locale "ar" has no jalali month names');
        JalaliView::parseExact('19 فروردين 1405', 'j F Y', null, 'ar');
    }

    public function testUnknownLocaleIsAProgrammerError(): void
    {
        $this->expectException(InvalidArgumentException::class);
        GregorianView::parseExact('8 April 2026', 'j F Y', null, 'xx');
    }

    public function testTryParseExactForwardsLocale(): void
    {
        $this->assertNotNull(HijriUmmAlQuraView::tryParseExact('20 شوال 1447', 'j F Y', null, 'ar'));
        $this->assertNull(HijriUmmAlQuraView::tryParseExact('20 Foo 1447', 'j F Y', null, 'ar'));
    }

    public function testNameAtEndOfInput(): void
    {
        $g = GregorianView::parseExact('15.03.2026 March', 'd.m.Y F')->gregorian();
        $this->assertSame([2026, 3, 15], [$g->year(), $g->month(), $g->day()]);
    }

    /**
     * A name followed by any letter is a different word, whatever the
     * letter's UTF-8 length: 2-byte é, 3-byte क (lead byte 0xE0) and 4-byte
     * 𠀀 (lead byte 0xF0).
     */
    #[DataProvider('letterAfterNameProvider')]
    public function testNameFollowedByAnyLetterIsRejected(string $letter): void
    {
        $text = "15 March{$letter} 2026";
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage(sprintf(
            'Cannot parse "%s" with format "j F Y": expected a month name for "F" at position 3',
            $text,
        ));
        GregorianView::parseExact($text, 'j F Y');
    }

    /** @return iterable<string, array{string}> */
    public static function letterAfterNameProvider(): iterable
    {
        yield 'Latin-1 é' => ['é'];
        yield 'Devanagari क' => ["\u{0915}"];
        yield 'Hangul 가' => ["\u{AC00}"];
        yield 'CJK extension B 𠀀' => ["\u{20000}"];
    }

    public function testNameFollowedByEmojiEndsAtTheEmoji(): void
    {
        // An emoji is not a letter, so "March" matches; the 4-byte emoji
        // then fails the literal space.
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('expected literal " " at position 8');
        GregorianView::parseExact("15 March\u{1F600} 2026", 'j F Y');
    }

    /**
     * Only the one character after a name decides the word boundary: a
     * symbol followed by a letter still ends the name.
     */
    #[DataProvider('symbolThenLetterProvider')]
    public function testOnlyTheNextCharacterDecidesTheBoundary(string $symbol): void
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('expected literal " " at position 8');
        GregorianView::parseExact("15 March{$symbol}x 2026", 'j F Y');
    }

    /** @return iterable<string, array{string}> */
    public static function symbolThenLetterProvider(): iterable
    {
        yield '2-byte ×' => ["\u{00D7}"];
        yield '3-byte €' => ["\u{20AC}"];
        yield '4-byte 😀' => ["\u{1F600}"];
    }

    public function testShorterNameIsTriedWhenALongerOneRunsIntoALetter(): void
    {
        // A custom locale where one name ("May X", June) extends another
        // ("May") past a non-letter: in "May Xmas" the longer name runs
        // into "m", so the shorter one must still be tried.
        LocaleRegistry::register('qpn', new class extends EnglishLocale {
            protected function longMonthTable(): array
            {
                $table = parent::longMonthTable();
                $table['gregorian'][6] = 'May X';
                return $table;
            }
        });

        $g = GregorianView::parseExact('15 May Xmas 2026', 'j F X\m\a\s Y', null, 'qpn')->gregorian();
        $this->assertSame([2026, 5, 15], [$g->year(), $g->month(), $g->day()]);
    }
}
