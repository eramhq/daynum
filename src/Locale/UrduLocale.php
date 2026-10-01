<?php

declare(strict_types=1);

namespace Eram\Daynum\Locale;

use Eram\Daynum\Season;
use Eram\Daynum\WeekDay;

/**
 * Urdu (`ur`), matching ICU `ur-PK`.
 *
 * The month and weekday tables come from `tools/dump-locale-tables.php`.
 * ICU's Jalali month names are transliterations of the Persian ones
 * (فروردن for Farvardin) and are kept as ICU ships them.
 */
class UrduLocale extends AbstractTableLocale
{
    private const LONG_MONTHS = [
        'gregorian' => [
            1 => 'جنوری', 2 => 'فروری', 3 => 'مارچ', 4 => 'اپریل',
            5 => 'مئی', 6 => 'جون', 7 => 'جولائی', 8 => 'اگست',
            9 => 'ستمبر', 10 => 'اکتوبر', 11 => 'نومبر', 12 => 'دسمبر',
        ],
        'jalali' => [
            1 => 'فروردن', 2 => 'آرڈبائش', 3 => 'خداداد', 4 => 'تیر',
            5 => 'مرداد', 6 => 'شہریوار', 7 => 'مہر', 8 => 'ابان',
            9 => 'آزر', 10 => 'ڈے', 11 => 'بہمن', 12 => 'اسفند',
        ],
        'hijri' => [
            1 => 'محرم', 2 => 'صفر', 3 => 'ر بیع الاول', 4 => 'ر بیع الثانی',
            5 => 'جمادی الاول', 6 => 'جمادی الثانی', 7 => 'رجب', 8 => 'شعبان',
            9 => 'رمضان', 10 => 'شوال', 11 => 'ذوالقعدۃ', 12 => 'ذوالحجۃ',
        ],
    ];

    private const SHORT_MONTHS = [
        'gregorian' => [
            1 => 'جنوری', 2 => 'فروری', 3 => 'مارچ', 4 => 'اپریل',
            5 => 'مئی', 6 => 'جون', 7 => 'جولائی', 8 => 'اگست',
            9 => 'ستمبر', 10 => 'اکتوبر', 11 => 'نومبر', 12 => 'دسمبر',
        ],
        'jalali' => [
            1 => 'فروردن', 2 => 'آرڈبائش', 3 => 'خداداد', 4 => 'تیر',
            5 => 'مرداد', 6 => 'شہریوار', 7 => 'مہر', 8 => 'ابان',
            9 => 'آزر', 10 => 'ڈے', 11 => 'بہمن', 12 => 'اسفند',
        ],
        'hijri' => [
            1 => 'محرم', 2 => 'صفر', 3 => 'ربیع الاوّل', 4 => 'ربیع الثانی',
            5 => 'جمادی الاوّل', 6 => 'جمادی الثانی', 7 => 'رجب', 8 => 'شعبان',
            9 => 'رمضان', 10 => 'شوال', 11 => 'ذوالقعدۃ', 12 => 'ذوالحجۃ',
        ],
    ];

    /** Indexed by PHP day-of-week: Sunday=0..Saturday=6. */
    private const LONG_WEEKDAYS = [
        0 => 'اتوار', 1 => 'پیر', 2 => 'منگل', 3 => 'بدھ',
        4 => 'جمعرات', 5 => 'جمعہ', 6 => 'ہفتہ',
    ];

    /** ICU's `ur` `EEE` output equals `EEEE`. */
    private const SHORT_WEEKDAYS = self::LONG_WEEKDAYS;

    public function tag(): string
    {
        return 'ur';
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

    /** CLDR `ur` uses the Latin AM/PM; `$uppercase` picks the case, as in English. */
    public function meridiem(bool $isPm, bool $uppercase): string
    {
        if ($uppercase) {
            return $isPm ? 'PM' : 'AM';
        }
        return $isPm ? 'pm' : 'am';
    }

    /** Urdu has no English-style ordinal suffix. */
    public function ordinalSuffix(int $day): string
    {
        return '';
    }

    /** Pakistan (CLDR `PK`): Sunday start, Saturday–Sunday weekend. */
    public function firstDayOfWeek(): WeekDay
    {
        return WeekDay::Sunday;
    }

    public function weekendDays(): array
    {
        return [WeekDay::Saturday, WeekDay::Sunday];
    }

    /**
     * Noun forms per unit, direction and CLDR plural category. Only a few
     * units inflect, and "1 hour" differs by direction (گھنٹہ پہلے, but
     * گھنٹے میں).
     */
    private const RELATIVE_NOUNS = [
        'second' => ['past' => ['one' => 'سیکنڈ', 'other' => 'سیکنڈ'], 'future' => ['one' => 'سیکنڈ', 'other' => 'سیکنڈ']],
        'minute' => ['past' => ['one' => 'منٹ',   'other' => 'منٹ'],   'future' => ['one' => 'منٹ',   'other' => 'منٹ']],
        'hour'   => ['past' => ['one' => 'گھنٹہ', 'other' => 'گھنٹے'], 'future' => ['one' => 'گھنٹے', 'other' => 'گھنٹے']],
        'day'    => ['past' => ['one' => 'دن',    'other' => 'دنوں'],  'future' => ['one' => 'دن',    'other' => 'دنوں']],
        'week'   => ['past' => ['one' => 'ہفتہ',  'other' => 'ہفتے'],  'future' => ['one' => 'ہفتہ',  'other' => 'ہفتے']],
        'month'  => ['past' => ['one' => 'مہینہ', 'other' => 'مہینے'], 'future' => ['one' => 'مہینہ', 'other' => 'مہینے']],
        'year'   => ['past' => ['one' => 'سال',   'other' => 'سال'],   'future' => ['one' => 'سال',   'other' => 'سال']],
    ];

    /** "3 دنوں پہلے" (3 days ago), "3 دنوں میں" (in 3 days). */
    public function relativeTime(int $value, string $unit, bool $future): string
    {
        self::assertRelativeTimeArgs($value, $unit);
        $noun = self::RELATIVE_NOUNS[$unit][$future ? 'future' : 'past'][$value === 1 ? 'one' : 'other'];
        return "{$value} {$noun} " . ($future ? 'میں' : 'پہلے');
    }

    public function relativeTimeNow(): string
    {
        return 'اب';
    }

    /** Not in CLDR, so not checked against ICU; native-speaker review welcome. */
    public function seasonName(Season $season): string
    {
        return match ($season) {
            Season::Spring => 'بہار',
            Season::Summer => 'گرمی',
            Season::Autumn => 'خزاں',
            Season::Winter => 'سردی',
        };
    }
}
