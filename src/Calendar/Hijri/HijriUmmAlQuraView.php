<?php

declare(strict_types=1);

namespace Eram\Daynum\Calendar\Hijri;

use Eram\Daynum\Calendar;
use Eram\Daynum\Calendar\AbstractCalendarView;

/**
 * View of an {@see \Eram\Daynum\CivilDateTime} as an Umm al-Qura (Saudi KACST) Hijri date.
 *
 * Use `$dateTime->hijri()` to construct; never `new` directly. The view will
 * throw {@see \Eram\Daynum\Exception\UmmAlQuraOutOfRangeException} when asked to
 * read components for a JDN outside the bundled UAQ table — use
 * `$dateTime->hijriCivil()` in that case instead.
 */
final class HijriUmmAlQuraView extends AbstractCalendarView
{
    public function calendar(): Calendar
    {
        return HijriUmmAlQuraCalendar::instance();
    }

    protected static function calendarInstance(): Calendar
    {
        return HijriUmmAlQuraCalendar::instance();
    }

    protected function defaultFormat(): string
    {
        return 'Y/m/d';
    }
}
