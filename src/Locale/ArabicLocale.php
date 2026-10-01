<?php

declare(strict_types=1);

namespace Eram\Daynum\Locale;

use Eram\Daynum\WeekDay;

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

    /** Saudi Arabia (CLDR `ar-SA`): Sunday start, Friday–Saturday weekend. */
    public function firstDayOfWeek(): WeekDay
    {
        return WeekDay::Sunday;
    }

    public function weekendDays(): array
    {
        return [WeekDay::Friday, WeekDay::Saturday];
    }

    /**
     * Noun phrases per unit and CLDR plural category (`%d` = the number).
     * Arabic counts take different noun forms: dual (يومين), plural after
     * 3–10 (٣ أيام), accusative singular after 11–99 (١١ يومًا).
     */
    private const RELATIVE_PHRASES = [
        'second' => ['zero' => '%d ثانية', 'one' => 'ثانية واحدة', 'two' => 'ثانيتين', 'few' => '%d ثوانٍ',  'many' => '%d ثانية',   'other' => '%d ثانية'],
        'minute' => ['zero' => '%d دقيقة', 'one' => 'دقيقة واحدة', 'two' => 'دقيقتين', 'few' => '%d دقائق', 'many' => '%d دقيقة',   'other' => '%d دقيقة'],
        'hour'   => ['zero' => '%d ساعة',  'one' => 'ساعة واحدة',  'two' => 'ساعتين',  'few' => '%d ساعات', 'many' => '%d ساعة',    'other' => '%d ساعة'],
        'day'    => ['zero' => '%d يوم',   'one' => 'يوم واحد',    'two' => 'يومين',   'few' => '%d أيام',  'many' => '%d يومًا',   'other' => '%d يوم'],
        'week'   => ['zero' => '%d أسبوع', 'one' => 'أسبوع واحد',  'two' => 'أسبوعين', 'few' => '%d أسابيع', 'many' => '%d أسبوعًا', 'other' => '%d أسبوع'],
        'month'  => ['zero' => '%d شهر',   'one' => 'شهر واحد',    'two' => 'شهرين',   'few' => '%d أشهر',  'many' => '%d شهرًا',   'other' => '%d شهر'],
        'year'   => ['zero' => '%d سنة',   'one' => 'سنة واحدة',   'two' => 'سنتين',   'few' => '%d سنوات', 'many' => '%d سنة',     'other' => '%d سنة'],
    ];

    public function relativeTime(int $value, string $unit, bool $future): string
    {
        self::assertRelativeTimeArgs($value, $unit);
        $category = self::pluralCategory($value);
        $phrase = self::RELATIVE_PHRASES[$unit][$category];
        // CLDR spells the past "few" seconds with kasra (ثوانِ) and the
        // future with kasratan (ثوانٍ); follow it so output matches ICU.
        if ($unit === 'second' && $category === 'few' && !$future) {
            $phrase = '%d ثوانِ';
        }
        return ($future ? 'خلال ' : 'قبل ') . sprintf($phrase, $value);
    }

    /**
     * CLDR plural category for Arabic cardinals.
     */
    private static function pluralCategory(int $n): string
    {
        $mod100 = $n % 100;
        return match (true) {
            $n === 0 => 'zero',
            $n === 1 => 'one',
            $n === 2 => 'two',
            $mod100 >= 3 && $mod100 <= 10 => 'few',
            $mod100 >= 11 => 'many',
            default => 'other',
        };
    }
}
