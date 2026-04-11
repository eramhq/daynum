<?php

declare(strict_types=1);

namespace Eram\Daynum\Calendar\Jalali;

use Eram\Daynum\Calendar;
use Eram\Daynum\Calendar\AbstractCalendarView;

/**
 * View of an {@see \Eram\Daynum\Instant} as a Jalali (Shamsi / Solar Hijri) date.
 *
 * Use `$instant->jalali()` to construct; never `new` directly.
 */
final class JalaliView extends AbstractCalendarView
{
    public function calendar(): Calendar
    {
        return JalaliCalendar::instance();
    }

    protected static function calendarInstance(): Calendar
    {
        return JalaliCalendar::instance();
    }

    protected function defaultFormat(): string
    {
        return 'Y/m/d';
    }
}
