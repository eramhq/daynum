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
     * 2026-04-08 Wednesday 14:30:45, Gregorian, English locale — the base
     * sample used by most token-coverage tests. Pass `$overrides` to tweak
     * specific fields without re-typing the whole 16-argument constructor.
     */
    private function sampleContext(array $overrides = []): FormatContext
    {
        $defaults = [
            'locale' => new EnglishLocale(),
            'calendarName' => 'gregorian',
            'year' => 2026,
            'month' => 4,
            'day' => 8,
            'hour' => 14,
            'minute' => 30,
            'second' => 45,
            'dayOfWeek' => 3,       // Wednesday (Sun=0)
            'dayOfWeekIso' => 3,    // Wednesday (Mon=1)
            'daysInMonth' => 30,
            'dayOfYear' => 98,      // 31 (Jan) + 28 (Feb) + 31 (Mar) + 8
            'weekOfYear' => 15,     // 2026-01-01 is Thursday → ISO week 1 is Jan 1–7
            'weekBasedYear' => 2026, // mid-year: `o` and `Y` agree
            'isLeapYear' => false,
            'tzLabel' => 'UTC',
            'digitScript' => DigitTransliterator::LATN,
        ];
        return new FormatContext(...[...$defaults, ...$overrides]);
    }

    /**
     * Jalali 1405-01-19 (equivalent to 2026-04-08) 14:30 under the Persian
     * locale with Perso-Arabic digits. Shared base for digit-script tests.
     */
    private function jalaliPersianContext(array $overrides = []): FormatContext
    {
        return $this->sampleContext([
            'locale' => new PersianLocale(),
            'calendarName' => 'jalali',
            'year' => 1405, 'month' => 1, 'day' => 19,
            'hour' => 14, 'minute' => 30, 'second' => 0,
            'daysInMonth' => 31,
            'dayOfYear' => 19,
            'weekOfYear' => 3,
            'tzLabel' => 'Asia/Tehran',
            'digitScript' => DigitTransliterator::PERSIAN,
            ...$overrides,
        ]);
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
        yield 'g 12h unpadded'     => ['g', '2'];           // hour 14 → 2 PM
        yield 'h 12h padded'       => ['h', '02'];
        yield 'W ISO week'         => ['W', '15'];
        yield 'WW repeated'        => ['WW', '1515'];
        yield 'o week-based year'  => ['o', '2026'];        // mid-year: matches Y
        yield 'o-W combined'       => ['o-\WW', '2026-W15'];
        yield 'oY adjacent'        => ['oY', '20262026'];   // mid-year: both 2026
        yield 'S ordinal en'       => ['S', 'th'];          // day 8 → th
        yield 'jS ordinal'         => ['jS', '8th'];
        yield 'dS padded-ordinal'  => ['dS', '08th'];
        yield 'z-W combined'       => ['z-W', '97-15'];
        yield 'Wz adjacent'        => ['Wz', '1597'];
        yield 'gh adjacent'        => ['gh', '202'];        // g=2, h=02
        yield 'full pattern'       => ['Y-m-d H:i:s', '2026-04-08 14:30:45'];
        yield 'human pattern'      => ['l, j F Y', 'Wednesday, 8 April 2026'];
        yield 'human ordinal'      => ['l jS F Y', 'Wednesday 8th April 2026'];
    }

    public function testZTokenRespectsBackslashEscape(): void
    {
        // `\z` → literal `z`. Existing testBackslashEscape already covers
        // escape-then-token adjacency; this test pins the minimal `\z` case.
        $this->assertSame('z', DateTokenFormatter::format('\z', $this->sampleContext()));
    }

    public function testWTokenRespectsBackslashEscape(): void
    {
        // `\W` → literal `W`. Exercises the false-positive path of the
        // `patternContainsUnescaped` guard threaded through
        // AbstractCalendarView::format — the view-level test in InstantTest
        // pins the full path; this test pins the tokenizer half.
        $this->assertSame('W', DateTokenFormatter::format('\W', $this->sampleContext()));
    }

    public function testOTokenRespectsBackslashEscape(): void
    {
        // `\o` → literal `o`. The companion to `\W` / `\z` — same
        // escape-aware guard, same benign false-positive shape.
        $this->assertSame('o', DateTokenFormatter::format('\o', $this->sampleContext()));
    }

    /**
     * @dataProvider isoWeekYearCases
     */
    public function testOTokenCrossYearThursdayRule(
        int $year, int $month, int $day, int $dow, int $isoDow,
        int $week, int $weekBasedYear, string $expected
    ): void {
        $ctx = $this->sampleContext([
            'year' => $year, 'month' => $month, 'day' => $day,
            'dayOfWeek' => $dow, 'dayOfWeekIso' => $isoDow,
            'weekOfYear' => $week,
            'weekBasedYear' => $weekBasedYear,
        ]);
        $this->assertSame($expected, DateTokenFormatter::format('o-\WW', $ctx));
    }

    /**
     * The whole point of `o`: around Jan 1 / Dec 31, `o` can differ from
     * `Y` by ±1. Cross-checked with `date('o-\WW', strtotime(...))`.
     *
     * @return iterable<string, array{int,int,int,int,int,int,int,string}>
     */
    public static function isoWeekYearCases(): iterable
    {
        // [year, month, day, dayOfWeek, isoDow, weekOfYear, weekBasedYear, expected]
        yield '2024-12-30 Mon (owned by 2025)' => [2024, 12, 30, 1, 1,  1, 2025, '2025-W01'];
        yield '2023-01-01 Sun (owned by 2022)' => [2023,  1,  1, 0, 7, 52, 2022, '2022-W52'];
        yield '2026-12-31 Thu (53-week year)'  => [2026, 12, 31, 4, 4, 53, 2026, '2026-W53'];
        yield '2026-04-08 Wed (mid-year)'      => [2026,  4,  8, 3, 3, 15, 2026, '2026-W15'];
    }

    public function testOAndYDivergeAtCrossYearBoundary(): void
    {
        // 2024-12-30 Monday: `Y`=2024 but `o`=2025. This is the user-facing
        // bug `o` exists to fix — `Y-W` would render `2024-01` (misleading)
        // whereas `o-\WW` renders `2025-W01` (correct).
        $ctx = $this->sampleContext([
            'year' => 2024, 'month' => 12, 'day' => 30,
            'dayOfWeek' => 1, 'dayOfWeekIso' => 1,
            'weekOfYear' => 1,
            'weekBasedYear' => 2025,
        ]);
        $this->assertSame('20252024', DateTokenFormatter::format('oY', $ctx));
    }

    public function testSTokenRespectsBackslashEscape(): void
    {
        $this->assertSame('S', DateTokenFormatter::format('\S', $this->sampleContext()));
    }

    public function testGAndHTokenBackslashEscape(): void
    {
        $this->assertSame('gh', DateTokenFormatter::format('\g\h', $this->sampleContext()));
    }

    /**
     * @dataProvider twelveHourBoundaries
     */
    public function testGAndHTwelveHourBoundaries(int $hour, string $g, string $h): void
    {
        // Midnight (0) and noon (12) are the load-bearing cases for the
        // `% 12 ?: 12` idiom in DateTokenFormatter — without the `?: 12`
        // fallback they would both render as `0`.
        $ctx = $this->sampleContext(['hour' => $hour]);
        $this->assertSame($g, DateTokenFormatter::format('g', $ctx));
        $this->assertSame($h, DateTokenFormatter::format('h', $ctx));
    }

    /**
     * @return iterable<string, array{int, string, string}>
     */
    public static function twelveHourBoundaries(): iterable
    {
        yield 'midnight'    => [0,  '12', '12'];
        yield '1 AM'        => [1,  '1',  '01'];
        yield '11 AM'       => [11, '11', '11'];
        yield 'noon'        => [12, '12', '12'];
        yield '1 PM'        => [13, '1',  '01'];
        yield '11 PM'       => [23, '11', '11'];
    }

    public function testGAndHInMeridiemPatterns(): void
    {
        $this->assertSame('02:30:45 PM', DateTokenFormatter::format('h:i:s A', $this->sampleContext()));

        $midnight = $this->sampleContext(['hour' => 0, 'minute' => 0, 'second' => 0]);
        $this->assertSame('12:00 am', DateTokenFormatter::format('g:i a', $midnight));

        $endOfDay = $this->sampleContext(['hour' => 23, 'minute' => 59, 'second' => 59]);
        $this->assertSame('11:59:59 PM', DateTokenFormatter::format('h:i:s A', $endOfDay));
    }

    public function testGAndHWithPersianDigits(): void
    {
        $ctx = $this->jalaliPersianContext();
        $this->assertSame('۲:۳۰', DateTokenFormatter::format('g:i', $ctx));
        $this->assertSame('۰۲:۳۰', DateTokenFormatter::format('h:i', $ctx));
    }

    /**
     * @dataProvider isoWeekCases
     */
    public function testWTokenAcrossGregorianBoundaries(
        int $year, int $month, int $day, int $dow, int $isoDow, int $week, string $expected
    ): void {
        $ctx = $this->sampleContext([
            'year' => $year, 'month' => $month, 'day' => $day,
            'dayOfWeek' => $dow, 'dayOfWeekIso' => $isoDow,
            'weekOfYear' => $week,
        ]);
        $this->assertSame($expected, DateTokenFormatter::format('W', $ctx));
    }

    /**
     * ISO week-number cases including the cross-year Thursday rule:
     * 2024-12-30 is W=01 (belongs to 2025) and 2023-01-01 is W=52
     * (belongs to 2022). Cross-checked with `date('W', strtotime(...))`.
     *
     * @return iterable<string, array{int,int,int,int,int,int,string}>
     */
    public static function isoWeekCases(): iterable
    {
        // [year, month, day, dayOfWeek (Sun=0), dayOfWeekIso, weekOfYear, expected]
        yield '2026-01-05 Mon W=02'   => [2026, 1,  5, 1, 1,  2, '02'];
        yield '2024-12-30 Mon W=01'   => [2024, 12, 30, 1, 1, 1, '01'];
        yield '2026-12-31 Thu W=53'   => [2026, 12, 31, 4, 4, 53, '53'];
        yield '2023-01-01 Sun W=52'   => [2023, 1,  1, 0, 7, 52, '52'];
    }

    public function testSTokenFullPatternEnglish(): void
    {
        $this->assertSame('8th April 2026', DateTokenFormatter::format('jS F Y', $this->sampleContext()));
        $this->assertSame('Wednesday 8th April 2026', DateTokenFormatter::format('l jS F Y', $this->sampleContext()));
    }

    public function testSTokenFullPatternPersianHasNoResidue(): void
    {
        // Persian's empty ordinal suffix must not produce stray characters —
        // `jS F Y` should render identically to `j F Y`.
        $this->assertSame(
            '۱۹ فروردین ۱۴۰۵',
            DateTokenFormatter::format('jS F Y', $this->jalaliPersianContext())
        );
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
                weekOfYear: 1,
                weekBasedYear: $year,
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
            weekOfYear: 3,
            weekBasedYear: 1405,
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
            weekOfYear: 1,
            weekBasedYear: -44,
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
            weekOfYear: 1,
            weekBasedYear: 0,
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
            weekOfYear: 3,
            weekBasedYear: 1405,
            isLeapYear: false,
            tzLabel: 'Asia/Tehran',
            digitScript: DigitTransliterator::PERSIAN,
        );
        $this->assertSame('۱۴۰۵/۰۱/۱۹', DateTokenFormatter::format('Y/m/d', $ctx));
        $this->assertSame('چهارشنبه ۱۹ فروردین ۱۴۰۵', DateTokenFormatter::format('l j F Y', $ctx));
    }
}
