<?php

declare(strict_types=1);

namespace Daynum\Tests\Unit;

use Daynum\Locale\EnglishLocale;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class EnglishLocaleTest extends TestCase
{
    public function testTagIsEn(): void
    {
        $this->assertSame('en', (new EnglishLocale())->tag());
    }

    public function testGregorianMonthNames(): void
    {
        $locale = new EnglishLocale();
        $this->assertSame('January', $locale->monthName('gregorian', 1));
        $this->assertSame('December', $locale->monthName('gregorian', 12));
        $this->assertSame('Jan', $locale->monthNameShort('gregorian', 1));
        $this->assertSame('Dec', $locale->monthNameShort('gregorian', 12));
    }

    public function testJalaliMonthNamesAreTransliterated(): void
    {
        $locale = new EnglishLocale();
        $this->assertSame('Farvardin', $locale->monthName('jalali', 1));
        $this->assertSame('Esfand', $locale->monthName('jalali', 12));
        // Jalali has no traditional abbreviation; short == long.
        $this->assertSame('Farvardin', $locale->monthNameShort('jalali', 1));
    }

    public function testHijriMonthNamesMatchIcuTransliteration(): void
    {
        $locale = new EnglishLocale();
        // Long form — every month.
        $long = [
            1  => 'Muharram',    2  => 'Safar',       3  => 'Rabiʻ I',
            4  => 'Rabiʻ II',    5  => 'Jumada I',    6  => 'Jumada II',
            7  => 'Rajab',       8  => 'Shaʻban',     9  => 'Ramadan',
            10 => 'Shawwal',     11 => 'Dhuʻl-Qiʻdah', 12 => 'Dhuʻl-Hijjah',
        ];
        foreach ($long as $m => $expected) {
            $this->assertSame($expected, $locale->monthName('hijri', $m));
        }
        // Short form is distinct for English Hijri.
        $short = [
            1  => 'Muh.',    2  => 'Saf.',    3  => 'Rab. I',
            4  => 'Rab. II', 5  => 'Jum. I',  6  => 'Jum. II',
            7  => 'Raj.',    8  => 'Sha.',    9  => 'Ram.',
            10 => 'Shaw.',   11 => 'Dhuʻl-Q.', 12 => 'Dhuʻl-H.',
        ];
        foreach ($short as $m => $expected) {
            $this->assertSame($expected, $locale->monthNameShort('hijri', $m));
        }
    }

    public function testWeekdayNames(): void
    {
        $locale = new EnglishLocale();
        $this->assertSame('Sunday', $locale->weekdayName(0));
        $this->assertSame('Saturday', $locale->weekdayName(6));
        $this->assertSame('Sun', $locale->weekdayNameShort(0));
    }

    public function testMeridiem(): void
    {
        $locale = new EnglishLocale();
        $this->assertSame('am', $locale->meridiem(false, false));
        $this->assertSame('AM', $locale->meridiem(false, true));
        $this->assertSame('pm', $locale->meridiem(true, false));
        $this->assertSame('PM', $locale->meridiem(true, true));
    }

    public function testInvalidMonthThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new EnglishLocale())->monthName('gregorian', 13);
    }

    public function testInvalidCalendarThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new EnglishLocale())->monthName('hebrew', 1);
    }

    /**
     * Exhaustive day-1-to-31 coverage of the English ordinal suffix. Days
     * 11/12/13 are the load-bearing override: they take `th` despite ending
     * in 1/2/3.
     *
     * @dataProvider ordinalSuffixCases
     */
    public function testOrdinalSuffix(int $day, string $expected): void
    {
        $this->assertSame($expected, (new EnglishLocale())->ordinalSuffix($day));
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function ordinalSuffixCases(): iterable
    {
        $table = [
            1 => 'st', 2 => 'nd', 3 => 'rd', 4 => 'th', 5 => 'th',
            6 => 'th', 7 => 'th', 8 => 'th', 9 => 'th', 10 => 'th',
            11 => 'th', 12 => 'th', 13 => 'th', 14 => 'th', 15 => 'th',
            16 => 'th', 17 => 'th', 18 => 'th', 19 => 'th', 20 => 'th',
            21 => 'st', 22 => 'nd', 23 => 'rd', 24 => 'th', 25 => 'th',
            26 => 'th', 27 => 'th', 28 => 'th', 29 => 'th', 30 => 'th',
            31 => 'st',
        ];
        foreach ($table as $day => $suffix) {
            yield "day {$day}" => [$day, $suffix];
        }
    }
}
