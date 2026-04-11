<?php

declare(strict_types=1);

namespace Eram\Daynum\Calendar\Gregorian;

use Eram\Daynum\Calendar;
use Eram\Daynum\Calendar\AbstractCalendarView;

/**
 * View of an {@see \Eram\Daynum\Instant} as a proleptic Gregorian date.
 *
 * Use `$instant->gregorian()` to construct; never `new` directly.
 */
final class GregorianView extends AbstractCalendarView
{
    public function calendar(): Calendar
    {
        return GregorianCalendar::instance();
    }

    protected static function calendarInstance(): Calendar
    {
        return GregorianCalendar::instance();
    }

    protected function defaultFormat(): string
    {
        return 'Y-m-d';
    }
}
