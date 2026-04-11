<?php

declare(strict_types=1);

namespace Eram\Daynum\Calendar\Hijri;

use Eram\Daynum\Calendar;
use Eram\Daynum\Calendar\AbstractCalendarView;

/**
 * View of an {@see \Eram\Daynum\Instant} as a tabular Hijri (arithmetic Islamic)
 * date.
 *
 * Use `$instant->hijriCivil()` to construct; never `new` directly. For the
 * Saudi Umm al-Qura calendar, use `$instant->hijri()` instead — this view
 * is the arithmetic fallback that always works for AH 1..9666.
 */
final class HijriCivilView extends AbstractCalendarView
{
    public function calendar(): Calendar
    {
        return HijriCivilCalendar::instance();
    }

    protected static function calendarInstance(): Calendar
    {
        return HijriCivilCalendar::instance();
    }

    protected function defaultFormat(): string
    {
        return 'Y/m/d';
    }
}
