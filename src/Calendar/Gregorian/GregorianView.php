<?php

declare(strict_types=1);

namespace Daynum\Calendar\Gregorian;

use Daynum\Calendar;
use Daynum\Calendar\AbstractCalendarView;

/**
 * View of an {@see \Daynum\Instant} as a proleptic Gregorian date.
 *
 * Use `$instant->gregorian()` to construct; never `new` directly.
 */
final class GregorianView extends AbstractCalendarView
{
    public function calendar(): Calendar
    {
        return GregorianCalendar::instance();
    }

    protected function defaultFormat(): string
    {
        return 'Y-m-d';
    }
}
