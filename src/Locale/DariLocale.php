<?php

declare(strict_types=1);

namespace Eram\Daynum\Locale;

use Eram\Daynum\Season;
use Eram\Daynum\WeekDay;

/**
 * Dari (`fa-AF`): Persian as written in Afghanistan, matching ICU `fa-AF`.
 *
 * Inherits everything from {@see PersianLocale} except:
 * - Jalali months use the zodiac names of the Afghan solar calendar
 *   (حمل Hamal, ثور Sawr, جوزا Jawza, … حوت Hut);
 * - Gregorian months use the English-derived spellings (جنوری, فبروری, …);
 * - the weekend is Thursday–Friday;
 * - autumn is خزان rather than پاییز.
 *
 * Also serves as the reference example of a custom locale: extend an
 * existing one, override the tables that differ, and register it.
 */
final class DariLocale extends PersianLocale
{
    private const JALALI_MONTHS = [
        1  => 'حمل',   2  => 'ثور',    3  => 'جوزا',
        4  => 'سرطان', 5  => 'اسد',    6  => 'سنبلهٔ',
        7  => 'میزان', 8  => 'عقرب',   9  => 'قوس',
        10 => 'جدی',   11 => 'دلو',    12 => 'حوت',
    ];

    private const GREGORIAN_MONTHS = [
        1  => 'جنوری',  2  => 'فبروری', 3  => 'مارچ',   4  => 'اپریل',
        5  => 'می',     6  => 'جون',    7  => 'جولای',  8  => 'اگست',
        9  => 'سپتمبر', 10 => 'اکتوبر', 11 => 'نومبر',  12 => 'دسمبر',
    ];

    /** ICU abbreviates only January, July and December. */
    private const GREGORIAN_MONTHS_SHORT = [1 => 'جنو', 7 => 'جول', 12 => 'دسم'] + self::GREGORIAN_MONTHS;

    public function tag(): string
    {
        return 'fa-AF';
    }

    protected function longMonthTable(): array
    {
        return ['jalali' => self::JALALI_MONTHS, 'gregorian' => self::GREGORIAN_MONTHS] + parent::longMonthTable();
    }

    protected function shortMonthTable(): array
    {
        return ['jalali' => self::JALALI_MONTHS, 'gregorian' => self::GREGORIAN_MONTHS_SHORT] + parent::shortMonthTable();
    }

    /** Afghanistan: Thursday–Friday weekend (CLDR `AF`). */
    public function weekendDays(): array
    {
        return [WeekDay::Thursday, WeekDay::Friday];
    }

    public function seasonName(Season $season): string
    {
        return $season === Season::Autumn ? 'خزان' : parent::seasonName($season);
    }
}
