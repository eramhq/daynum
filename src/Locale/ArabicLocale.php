<?php

declare(strict_types=1);

namespace Eram\Daynum\Locale;

/**
 * Arabic (`ar`) names for months and weekdays, matching ICU `ar-SA`.
 *
 * No Jalali month table: ICU's Arabic transliteration of the Persian names
 * is low quality (e.g., `فرفردن` for Farvardin). Calling a Jalali `F`/`M`
 * token under this locale therefore throws from the base class. Arabic
 * readers who need Jalali should use `withLocale('fa')` — Persian month
 * names render in the same Perso-Arabic script.
 */
final class ArabicLocale extends AbstractTableLocale
{
    private const LONG_MONTHS = [
        'gregorian' => [
            1  => 'يناير',  2  => 'فبراير', 3  => 'مارس',   4  => 'أبريل',
            5  => 'مايو',   6  => 'يونيو',  7  => 'يوليو',  8  => 'أغسطس',
            9  => 'سبتمبر', 10 => 'أكتوبر', 11 => 'نوفمبر', 12 => 'ديسمبر',
        ],
        'hijri' => [
            1  => 'محرم',         2  => 'صفر',           3  => 'ربيع الأول',
            4  => 'ربيع الآخر',   5  => 'جمادى الأولى',  6  => 'جمادى الآخرة',
            7  => 'رجب',          8  => 'شعبان',         9  => 'رمضان',
            10 => 'شوال',         11 => 'ذو القعدة',     12 => 'ذو الحجة',
        ],
    ];

    /** ICU's `ar-SA` `MMM` output equals `MMMM` for both calendars. */
    private const SHORT_MONTHS = self::LONG_MONTHS;

    /** Indexed by PHP day-of-week: Sunday=0..Saturday=6. */
    private const LONG_WEEKDAYS = [
        0 => 'الأحد',   1 => 'الاثنين', 2 => 'الثلاثاء',
        3 => 'الأربعاء', 4 => 'الخميس',  5 => 'الجمعة',
        6 => 'السبت',
    ];

    /** Arabic has no traditional weekday abbreviations; short == long. */
    private const SHORT_WEEKDAYS = self::LONG_WEEKDAYS;

    public function tag(): string
    {
        return 'ar';
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

    /** Arabic script is unicase; `$uppercase` is ignored. */
    public function meridiem(bool $isPm, bool $uppercase): string
    {
        return $isPm ? 'م' : 'ص';
    }

    /**
     * Arabic has no native English-style ordinal suffix. Returning the empty
     * string lets `jS F Y` render cleanly without leaving `th` residue
     * inside Arabic-script output.
     */
    public function ordinalSuffix(int $day): string
    {
        return '';
    }
}
