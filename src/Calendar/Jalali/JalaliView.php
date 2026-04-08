<?php

declare(strict_types=1);

namespace Daynum\Calendar\Jalali;

use Daynum\Calendar;
use Daynum\Calendar\AbstractCalendarView;

/**
 * View of an {@see \Daynum\Instant} as a Jalali (Shamsi / Solar Hijri) date.
 *
 * Use `$instant->jalali()` to construct; never `new` directly.
 */
final class JalaliView extends AbstractCalendarView
{
    public function calendar(): Calendar
    {
        return JalaliCalendar::instance();
    }

    protected function defaultFormat(): string
    {
        return 'Y/m/d';
    }
}
