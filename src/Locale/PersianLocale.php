<?php

declare(strict_types=1);

namespace Daynum\Locale;

/**
 * Persian (Farsi, `fa`) names for months and weekdays in both Gregorian and
 * Jalali forms.
 *
 * Weekdays are indexed by PHP's `date('w')` convention (Sunday=0). The Jalali
 * locale's human-facing week starts on Saturday, but the indexing is kept
 * uniform so every calendar uses the same underlying integer.
 */
final class PersianLocale extends AbstractTableLocale
{
    /**
     * Long form emits the Persian *ezafe* hamzeh (U+0654) on Gregorian month
     * names whose Persian spelling ends in a vowel — `ژانویهٔ`, `فوریهٔ`,
     * `مهٔ`, `ژوئیهٔ`. This matches ICU's `MMMM` output for the `fa` locale,
     * which reflects the grammatical "of" construction used when a day
     * number follows the month. Short form drops the ezafe.
     */
    private const LONG_MONTHS = [
        'gregorian' => [
            1  => 'ژانویهٔ', 2  => 'فوریهٔ',   3  => 'مارس',   4  => 'آوریل',
            5  => 'مهٔ',       6  => 'ژوئن',     7  => 'ژوئیهٔ', 8  => 'اوت',
            9  => 'سپتامبر',  10 => 'اکتبر',    11 => 'نوامبر', 12 => 'دسامبر',
        ],
        'jalali' => [
            1  => 'فروردین',   2  => 'اردیبهشت', 3  => 'خرداد',
            4  => 'تیر',        5  => 'مرداد',    6  => 'شهریور',
            7  => 'مهر',        8  => 'آبان',     9  => 'آذر',
            10 => 'دی',         11 => 'بهمن',     12 => 'اسفند',
        ],
        // Persian Hijri names match ICU fa-IR islamic-civil MMMM output.
        // Note months 11 and 12 contract to ذیقعده / ذیحجه without an
        // explicit `ال` — this is ICU's CLDR data for fa, not a typo.
        'hijri' => [
            1  => 'محرم',         2  => 'صفر',          3  => 'ربیع‌الاول',
            4  => 'ربیع‌الثانی',  5  => 'جمادی‌الاول', 6  => 'جمادی‌الثانی',
            7  => 'رجب',          8  => 'شعبان',        9  => 'رمضان',
            10 => 'شوال',         11 => 'ذیقعده',       12 => 'ذیحجه',
        ],
    ];

    private const SHORT_MONTHS = [
        'gregorian' => [
            1  => 'ژانویه',   2  => 'فوریه',    3  => 'مارس',   4  => 'آوریل',
            5  => 'مه',        6  => 'ژوئن',     7  => 'ژوئیه', 8  => 'اوت',
            9  => 'سپتامبر',   10 => 'اکتبر',    11 => 'نوامبر', 12 => 'دسامبر',
        ],
        // Jalali has no traditional abbreviation; short == long.
        'jalali' => self::LONG_MONTHS['jalali'],
        // Persian Hijri has no traditional abbreviation either.
        'hijri'  => self::LONG_MONTHS['hijri'],
    ];

    /** Indexed by PHP day-of-week: Sunday=0..Saturday=6. */
    private const LONG_WEEKDAYS = [
        0 => 'یکشنبه',  1 => 'دوشنبه',  2 => 'سه‌شنبه',
        3 => 'چهارشنبه', 4 => 'پنجشنبه', 5 => 'جمعه',
        6 => 'شنبه',
    ];

    /**
     * Persian weekday names have no traditional short form — the full
     * single-word names are already short enough — so `D` and `l` emit the
     * same string, matching ICU's behavior.
     */
    private const SHORT_WEEKDAYS = self::LONG_WEEKDAYS;

    public function tag(): string
    {
        return 'fa';
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
        return $isPm ? 'ب.ظ' : 'ق.ظ';
    }

    /**
     * Persian has no native English-style ordinal suffix. Returning the
     * empty string lets `jS F Y` render cleanly without leaving `th` residue
     * inside Perso-Arabic output.
     */
    public function ordinalSuffix(int $day): string
    {
        return '';
    }
}
