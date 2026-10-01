<?php

declare(strict_types=1);

namespace Eram\Daynum\Locale;

use Eram\Daynum\Season;
use Eram\Daynum\WeekDay;

/**
 * Pashto (`ps`), matching ICU `ps-AF`.
 *
 * Jalali months use the Pashto names of the Afghan solar months (وری,
 * غویی, … کب). The month and weekday tables come from
 * `tools/dump-locale-tables.php`; ICU's Hijri names mix scripts (`ربيع II`)
 * and are kept as ICU ships them.
 */
class PashtoLocale extends AbstractTableLocale
{
    private const LONG_MONTHS = [
        'gregorian' => [
            1 => 'جنوري', 2 => 'فبروري', 3 => 'مارچ', 4 => 'اپریل',
            5 => 'مۍ', 6 => 'جون', 7 => 'جولای', 8 => 'اګست',
            9 => 'سېپتمبر', 10 => 'اکتوبر', 11 => 'نومبر', 12 => 'دسمبر',
        ],
        'jalali' => [
            1 => 'وری', 2 => 'غویی', 3 => 'غبرگولی', 4 => 'چنگاښ',
            5 => 'زمری', 6 => 'وږی', 7 => 'تله', 8 => 'لړم',
            9 => 'لیندۍ', 10 => 'مرغومی', 11 => 'سلواغه', 12 => 'کب',
        ],
        'hijri' => [
            1 => 'محرم', 2 => 'صفر', 3 => 'ربیع اول', 4 => 'ربيع II',
            5 => 'جمادي اول', 6 => 'جماعه II', 7 => 'رجب', 8 => 'شعبان',
            9 => 'رمضان', 10 => 'شوال', 11 => 'ذي القعده', 12 => 'ذي الحج',
        ],
    ];

    private const SHORT_MONTHS = [
        'gregorian' => [
            1 => 'جنوري', 2 => 'فبروري', 3 => 'مارچ', 4 => 'اپریل',
            5 => 'مۍ', 6 => 'جون', 7 => 'جولای', 8 => 'اګست',
            9 => 'سېپتمبر', 10 => 'اکتوبر', 11 => 'نومبر', 12 => 'دسمبر',
        ],
        'jalali' => [
            1 => 'وری', 2 => 'غویی', 3 => 'غبرگولی', 4 => 'چنگاښ',
            5 => 'زمری', 6 => 'وږی', 7 => 'تله', 8 => 'لړم',
            9 => 'لیندۍ', 10 => 'مرغومی', 11 => 'سلواغه', 12 => 'کب',
        ],
        'hijri' => [
            1 => 'محرم', 2 => 'صفر', 3 => 'ربيع', 4 => 'ربيع II',
            5 => 'جماد I', 6 => 'جماد ۲', 7 => 'رجب', 8 => 'شعبان',
            9 => 'رمضان', 10 => 'شوال', 11 => 'دالقاعده', 12 => 'ذي الحج',
        ],
    ];

    /** Indexed by PHP day-of-week: Sunday=0..Saturday=6. */
    private const LONG_WEEKDAYS = [
        0 => 'يونۍ', 1 => 'دونۍ', 2 => 'درېنۍ', 3 => 'څلرنۍ',
        4 => 'پينځنۍ', 5 => 'جمعه', 6 => 'اونۍ',
    ];

    /** ICU's `ps` `EEE` output equals `EEEE`. */
    private const SHORT_WEEKDAYS = self::LONG_WEEKDAYS;

    public function tag(): string
    {
        return 'ps';
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
        return $isPm ? 'غ.و.' : 'غ.م.';
    }

    /** Pashto has no English-style ordinal suffix. */
    public function ordinalSuffix(int $day): string
    {
        return '';
    }

    /** Afghanistan (CLDR `AF`): Saturday start, Thursday–Friday weekend. */
    public function firstDayOfWeek(): WeekDay
    {
        return WeekDay::Saturday;
    }

    public function weekendDays(): array
    {
        return [WeekDay::Thursday, WeekDay::Friday];
    }

    /**
     * Noun forms per unit, direction and CLDR plural category. Pashto's
     * plural differs between "ago" (direct case: ورځې) and "in" (oblique
     * case after په: ورځو).
     */
    private const RELATIVE_NOUNS = [
        'second' => ['one' => 'ثانيه', 'past' => 'ثانيې',    'future' => 'ثانيو'],
        'minute' => ['one' => 'دقيقه', 'past' => 'دقيقې',    'future' => 'دقيقو'],
        'hour'   => ['one' => 'ساعت',  'past' => 'ساعتونه',  'future' => 'ساعتو'],
        'day'    => ['one' => 'ورځ',   'past' => 'ورځې',     'future' => 'ورځو'],
        'week'   => ['one' => 'اونۍ',  'past' => 'اونۍ',     'future' => 'اونيو'],
        'month'  => ['one' => 'مياشت', 'past' => 'مياشتې',   'future' => 'مياشتو'],
        'year'   => ['one' => 'کال',   'past' => 'کاله',     'future' => 'کالونو'],
    ];

    /** "3 ورځې مخکې" (3 days ago), "په 3 ورځو کې" (in 3 days). */
    public function relativeTime(int $value, string $unit, bool $future): string
    {
        self::assertRelativeTimeArgs($value, $unit);
        $noun = self::RELATIVE_NOUNS[$unit][$value === 1 ? 'one' : ($future ? 'future' : 'past')];
        return $future ? "په {$value} {$noun} کې" : "{$value} {$noun} مخکې";
    }

    public function relativeTimeNow(): string
    {
        return 'اوس';
    }

    /** Not in CLDR, so not checked against ICU; native-speaker review welcome. */
    public function seasonName(Season $season): string
    {
        return match ($season) {
            Season::Spring => 'پسرلی',
            Season::Summer => 'اوړی',
            Season::Autumn => 'منی',
            Season::Winter => 'ژمی',
        };
    }
}
