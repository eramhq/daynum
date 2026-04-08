<?php

declare(strict_types=1);

namespace Daynum\Tests\Unit;

use Daynum\Formatter\DateTokenFormatter;
use Daynum\Formatter\DigitTransliterator;
use Daynum\Formatter\FormatContext;
use Daynum\Locale\EnglishLocale;
use Daynum\Locale\PersianLocale;
use PHPUnit\Framework\TestCase;

final class DateTokenFormatterTest extends TestCase
{
    /**
     * 2026-04-08 Wednesday 14:30:45, Gregorian, English locale.
     */
    private function sampleContext(): FormatContext
    {
        return new FormatContext(
            locale: new EnglishLocale(),
            calendarName: 'gregorian',
            year: 2026,
            month: 4,
            day: 8,
            hour: 14,
            minute: 30,
            second: 45,
            dayOfWeek: 3,       // Wednesday (Sun=0)
            dayOfWeekIso: 3,    // Wednesday (Mon=1)
            daysInMonth: 30,
            dayOfYear: 98,      // 31 (Jan) + 28 (Feb) + 31 (Mar) + 8
            isLeapYear: false,
            tzLabel: 'UTC',
            digitScript: DigitTransliterator::LATN,
        );
    }

    /**
     * @dataProvider tokenExpectations
     */
    public function testTokens(string $pattern, string $expected): void
    {
        $this->assertSame($expected, DateTokenFormatter::format($pattern, $this->sampleContext()));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function tokenExpectations(): iterable
    {
        yield 'Y 4-digit year'     => ['Y', '2026'];
        yield 'y 2-digit year'     => ['y', '26'];
        yield 'm padded month'     => ['m', '04'];
        yield 'n month'            => ['n', '4'];
        yield 'd padded day'       => ['d', '08'];
        yield 'j day'              => ['j', '8'];
        yield 'D short weekday'    => ['D', 'Wed'];
        yield 'l long weekday'     => ['l', 'Wednesday'];
        yield 'F long month'       => ['F', 'April'];
        yield 'M short month'      => ['M', 'Apr'];
        yield 'G hour'             => ['G', '14'];
        yield 'H padded hour'      => ['H', '14'];
        yield 'i padded minute'    => ['i', '30'];
        yield 's padded second'    => ['s', '45'];
        yield 'N ISO dow'          => ['N', '3'];
        yield 'w PHP dow'          => ['w', '3'];
        yield 't days in month'    => ['t', '30'];
        yield 'L leap'             => ['L', '0'];
        yield 'T timezone'         => ['T', 'UTC'];
        yield 'e timezone name'    => ['e', 'UTC'];
        yield 'z day of year'      => ['z', '97'];
        yield 'zz repeated'        => ['zz', '9797'];
        yield 'z inline'           => ['Y-m-d (z)', '2026-04-08 (97)'];
        yield 'Yz adjacent'        => ['Yz', '202697'];
        yield 'full pattern'       => ['Y-m-d H:i:s', '2026-04-08 14:30:45'];
        yield 'human pattern'      => ['l, j F Y', 'Wednesday, 8 April 2026'];
    }

    public function testZTokenRespectsBackslashEscape(): void
    {
        // `\z` → literal `z`. Existing testBackslashEscape already covers
        // escape-then-token adjacency; this test pins the minimal `\z` case.
        $this->assertSame('z', DateTokenFormatter::format('\z', $this->sampleContext()));
    }

    public function testZTokenAcrossGregorianBoundaries(): void
    {
        // G2–G8: verify off-by-ones at year start, year end (leap & non-leap),
        // and the Feb 28/29 → Mar 1 transition in both leap and non-leap years.
        $cases = [
            // [year, month, day, daysInMonth, isLeapYear, dayOfYear, expected]
            [2023,  1,  1, 31, false,   1,   '0'],   // G2: Jan 1 non-leap
            [2023, 12, 31, 31, false, 365, '364'],   // G3: Dec 31 non-leap
            [2024, 12, 31, 31, true,  366, '365'],   // G4: Dec 31 leap
            [2023,  2, 28, 28, false,  59,  '58'],   // G5: Feb 28 non-leap
            [2024,  2, 29, 29, true,   60,  '59'],   // G6: Feb 29 leap
            [2024,  3,  1, 31, true,   61,  '60'],   // G7: Mar 1 leap
            [2023,  3,  1, 31, false,  60,  '59'],   // G8: Mar 1 non-leap
        ];
        foreach ($cases as [$year, $month, $day, $dim, $leap, $doy, $expected]) {
            $ctx = new FormatContext(
                locale: new EnglishLocale(),
                calendarName: 'gregorian',
                year: $year, month: $month, day: $day,
                hour: 0, minute: 0, second: 0,
                dayOfWeek: 0, dayOfWeekIso: 7,
                daysInMonth: $dim,
                dayOfYear: $doy,
                isLeapYear: $leap,
                tzLabel: null,
                digitScript: DigitTransliterator::LATN,
            );
            $this->assertSame(
                $expected,
                DateTokenFormatter::format('z', $ctx),
                "z for {$year}-{$month}-{$day}"
            );
        }
    }

    public function testZTokenWithPersianDigits(): void
    {
        // Jalali 1405-01-19: dayOfYear = 19, PHP-style z = 18, Persian digits = ۱۸.
        $ctx = new FormatContext(
            locale: new PersianLocale(),
            calendarName: 'jalali',
            year: 1405, month: 1, day: 19,
            hour: 0, minute: 0, second: 0,
            dayOfWeek: 3, dayOfWeekIso: 3,
            daysInMonth: 31,
            dayOfYear: 19,
            isLeapYear: false,
            tzLabel: 'Asia/Tehran',
            digitScript: DigitTransliterator::PERSIAN,
        );
        $this->assertSame('۱۸', DateTokenFormatter::format('z', $ctx));
    }

    public function testBackslashEscape(): void
    {
        $out = DateTokenFormatter::format('\Y\e\a\r Y', $this->sampleContext());
        $this->assertSame('Year 2026', $out);
    }

    public function testLiteralCharactersPassThrough(): void
    {
        $out = DateTokenFormatter::format('[Y/m/d]', $this->sampleContext());
        $this->assertSame('[2026/04/08]', $out);
    }

    public function testNegativeYearFormatsWithLeadingMinus(): void
    {
        $ctx = new FormatContext(
            locale: new EnglishLocale(),
            calendarName: 'gregorian',
            year: -44,
            month: 3,
            day: 15,
            hour: 0, minute: 0, second: 0,
            dayOfWeek: 0, dayOfWeekIso: 7,
            daysInMonth: 31,
            dayOfYear: 74,      // 31 (Jan) + 28 (Feb) + 15
            isLeapYear: false,
            tzLabel: null,
            digitScript: DigitTransliterator::LATN,
        );
        $this->assertSame('-0044-03-15', DateTokenFormatter::format('Y-m-d', $ctx));
    }

    public function testZeroYearFormatsAsZeroZero(): void
    {
        $ctx = new FormatContext(
            locale: new EnglishLocale(),
            calendarName: 'gregorian',
            year: 0, month: 1, day: 1,
            hour: 0, minute: 0, second: 0,
            dayOfWeek: 6, dayOfWeekIso: 6,
            daysInMonth: 31,
            dayOfYear: 1,
            isLeapYear: true,
            tzLabel: null,
            digitScript: DigitTransliterator::LATN,
        );
        $this->assertSame('0000', DateTokenFormatter::format('Y', $ctx));
    }

    public function testPersianLocalePersianDigits(): void
    {
        $ctx = new FormatContext(
            locale: new PersianLocale(),
            calendarName: 'jalali',
            year: 1405, month: 1, day: 19,
            hour: 14, minute: 30, second: 0,
            dayOfWeek: 3, dayOfWeekIso: 3,
            daysInMonth: 31,
            dayOfYear: 19,
            isLeapYear: false,
            tzLabel: 'Asia/Tehran',
            digitScript: DigitTransliterator::PERSIAN,
        );
        $this->assertSame('۱۴۰۵/۰۱/۱۹', DateTokenFormatter::format('Y/m/d', $ctx));
        $this->assertSame('چهارشنبه ۱۹ فروردین ۱۴۰۵', DateTokenFormatter::format('l j F Y', $ctx));
    }
}
