<?php

declare(strict_types=1);

namespace Eram\Daynum\Locale;

/**
 * English names for months and weekdays, in the Gregorian, Jalali, and Hijri
 * locale families.
 *
 * Jalali month names are rendered as transliterations of the Persian originals
 * (e.g., `Farvardin`, `Ordibehesht`). ICU emits the same full-name string for
 * both `MMM` and `MMMM` in the English persian-calendar locale, so the Jalali
 * short form table aliases the long one.
 *
 * Hijri spellings match ICU's `en-US-u-ca-islamic-civil` output — note that
 * unlike Jalali, English Hijri has distinct abbreviations (`Muh.`, `Saf.`,
 * `Rab. I`, etc.) and uses U+02BB MODIFIER LETTER TURNED COMMA (`ʻ`) for
 * transliterated ʿayn and hamza rather than the more common `ʾ` or `'`.
 */
final class EnglishLocale extends AbstractTableLocale
{
    private const LONG_MONTHS = [
        'gregorian' => [
            1  => 'January',   2  => 'February', 3  => 'March',    4  => 'April',
            5  => 'May',       6  => 'June',     7  => 'July',     8  => 'August',
            9  => 'September', 10 => 'October',  11 => 'November', 12 => 'December',
        ],
        'jalali' => [
            1  => 'Farvardin',  2  => 'Ordibehesht', 3  => 'Khordad',
            4  => 'Tir',        5  => 'Mordad',      6  => 'Shahrivar',
            7  => 'Mehr',       8  => 'Aban',        9  => 'Azar',
            10 => 'Dey',        11 => 'Bahman',      12 => 'Esfand',
        ],
        'hijri' => [
            1  => 'Muharram',    2  => 'Safar',       3  => 'Rabiʻ I',
            4  => 'Rabiʻ II',    5  => 'Jumada I',    6  => 'Jumada II',
            7  => 'Rajab',       8  => 'Shaʻban',     9  => 'Ramadan',
            10 => 'Shawwal',     11 => 'Dhuʻl-Qiʻdah', 12 => 'Dhuʻl-Hijjah',
        ],
    ];

    private const SHORT_MONTHS = [
        'gregorian' => [
            1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'May', 6 => 'Jun',
            7 => 'Jul', 8 => 'Aug', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec',
        ],
        // No traditional abbreviation; short == long to match ICU.
        'jalali' => self::LONG_MONTHS['jalali'],
        'hijri' => [
            1  => 'Muh.',    2  => 'Saf.',    3  => 'Rab. I',
            4  => 'Rab. II', 5  => 'Jum. I',  6  => 'Jum. II',
            7  => 'Raj.',    8  => 'Sha.',    9  => 'Ram.',
            10 => 'Shaw.',   11 => 'Dhuʻl-Q.', 12 => 'Dhuʻl-H.',
        ],
    ];

    /** Indexed by PHP day-of-week: Sunday=0..Saturday=6. */
    private const LONG_WEEKDAYS = [
        0 => 'Sunday',   1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday',
        4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday',
    ];

    private const SHORT_WEEKDAYS = [
        0 => 'Sun', 1 => 'Mon', 2 => 'Tue', 3 => 'Wed',
        4 => 'Thu', 5 => 'Fri', 6 => 'Sat',
    ];

    public function tag(): string
    {
        return 'en';
    }

    protected function longMonthTable(): array
    {
        return self::LONG_MONTHS;
    }

    protected function shortMonthTable(): array
    {
        return self::SHORT_MONTHS;
    }

    protected function longWeekdayTable(): array
    {
        return self::LONG_WEEKDAYS;
    }

    protected function shortWeekdayTable(): array
    {
        return self::SHORT_WEEKDAYS;
    }

    public function meridiem(bool $isPm, bool $uppercase): string
    {
        if ($uppercase) {
            return $isPm ? 'PM' : 'AM';
        }
        return $isPm ? 'pm' : 'am';
    }

    public function ordinalSuffix(int $day): string
    {
        // 11/12/13 take `th` despite ending in 1/2/3. The `% 100` lets the
        // rule apply to hypothetical 111/112/113 if a caller ever passes
        // values outside the 1..31 month range.
        $mod100 = $day % 100;
        if ($mod100 >= 11 && $mod100 <= 13) {
            return 'th';
        }
        return match ($day % 10) {
            1 => 'st',
            2 => 'nd',
            3 => 'rd',
            default => 'th',
        };
    }
}
