<?php

declare(strict_types=1);

namespace Eram\Daynum\Locale;

use Eram\Daynum\Season;
use Eram\Daynum\WeekDay;

/**
 * Turkish (`tr`), matching ICU `tr-TR`.
 *
 * The month and weekday tables come from `tools/dump-locale-tables.php`.
 * ICU's Jalali month names are Turkish transliterations of the Persian ones
 * (Ferverdin for Farvardin). Parsing folds Turkish case, so `ŞEVVAL` and
 * `şevval` both match Şevval.
 */
class TurkishLocale extends AbstractTableLocale
{
    private const LONG_MONTHS = [
        'gregorian' => [
            1 => 'Ocak', 2 => 'Şubat', 3 => 'Mart', 4 => 'Nisan',
            5 => 'Mayıs', 6 => 'Haziran', 7 => 'Temmuz', 8 => 'Ağustos',
            9 => 'Eylül', 10 => 'Ekim', 11 => 'Kasım', 12 => 'Aralık',
        ],
        'jalali' => [
            1 => 'Ferverdin', 2 => 'Ordibeheşt', 3 => 'Hordad', 4 => 'Tir',
            5 => 'Mordad', 6 => 'Şehriver', 7 => 'Mehr', 8 => 'Aban',
            9 => 'Azer', 10 => 'Dey', 11 => 'Behmen', 12 => 'Esfend',
        ],
        'hijri' => [
            1 => 'Muharrem', 2 => 'Safer', 3 => 'Rebiülevvel', 4 => 'Rebiülahir',
            5 => 'Cemaziyelevvel', 6 => 'Cemaziyelahir', 7 => 'Recep', 8 => 'Şaban',
            9 => 'Ramazan', 10 => 'Şevval', 11 => 'Zilkade', 12 => 'Zilhicce',
        ],
    ];

    private const SHORT_MONTHS = [
        'gregorian' => [
            1 => 'Oca', 2 => 'Şub', 3 => 'Mar', 4 => 'Nis',
            5 => 'May', 6 => 'Haz', 7 => 'Tem', 8 => 'Ağu',
            9 => 'Eyl', 10 => 'Eki', 11 => 'Kas', 12 => 'Ara',
        ],
        'jalali' => [
            1 => 'Ferverdin', 2 => 'Ordibeheşt', 3 => 'Hordad', 4 => 'Tir',
            5 => 'Mordad', 6 => 'Şehriver', 7 => 'Mehr', 8 => 'Aban',
            9 => 'Azer', 10 => 'Dey', 11 => 'Behmen', 12 => 'Esfend',
        ],
        'hijri' => [
            1 => 'Muhar.', 2 => 'Safer', 3 => 'R.evvel', 4 => 'R.ahir',
            5 => 'C.evvel', 6 => 'C.ahir', 7 => 'Recep', 8 => 'Şaban',
            9 => 'Ram.', 10 => 'Şevval', 11 => 'Zilkade', 12 => 'Zilhicce',
        ],
    ];

    /** Indexed by PHP day-of-week: Sunday=0..Saturday=6. */
    private const LONG_WEEKDAYS = [
        0 => 'Pazar', 1 => 'Pazartesi', 2 => 'Salı', 3 => 'Çarşamba',
        4 => 'Perşembe', 5 => 'Cuma', 6 => 'Cumartesi',
    ];

    private const SHORT_WEEKDAYS = [
        0 => 'Paz', 1 => 'Pzt', 2 => 'Sal', 3 => 'Çar',
        4 => 'Per', 5 => 'Cum', 6 => 'Cmt',
    ];

    public function tag(): string
    {
        return 'tr';
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

    /** ÖÖ (öğleden önce) / ÖS (öğleden sonra); `a` gives the lowercase form. */
    public function meridiem(bool $isPm, bool $uppercase): string
    {
        if ($uppercase) {
            return $isPm ? 'ÖS' : 'ÖÖ';
        }
        return $isPm ? 'ös' : 'öö';
    }

    /** Turkish ordinals are written "3." rather than with a suffix; none here. */
    public function ordinalSuffix(int $day): string
    {
        return '';
    }

    /** Turkey (CLDR `TR`): Monday start, Saturday–Sunday weekend. */
    public function firstDayOfWeek(): WeekDay
    {
        return WeekDay::Monday;
    }

    public function weekendDays(): array
    {
        return [WeekDay::Saturday, WeekDay::Sunday];
    }

    private const RELATIVE_UNITS = [
        'second' => 'saniye', 'minute' => 'dakika', 'hour' => 'saat',
        'day'    => 'gün',    'week'   => 'hafta',  'month' => 'ay', 'year' => 'yıl',
    ];

    /** Turkish nouns don't inflect after a number: "3 gün önce", "3 gün sonra". */
    public function relativeTime(int $value, string $unit, bool $future): string
    {
        self::assertRelativeTimeArgs($value, $unit);
        return $value . ' ' . self::RELATIVE_UNITS[$unit] . ($future ? ' sonra' : ' önce');
    }

    public function relativeTimeNow(): string
    {
        return 'şimdi';
    }

    /** Not in CLDR, so not checked against ICU; native-speaker review welcome. */
    public function seasonName(Season $season): string
    {
        return match ($season) {
            Season::Spring => 'İlkbahar',
            Season::Summer => 'Yaz',
            Season::Autumn => 'Sonbahar',
            Season::Winter => 'Kış',
        };
    }
}
