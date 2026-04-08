<?php

declare(strict_types=1);

namespace Daynum\Calendar\Hijri;

use Daynum\Calendar;
use Daynum\Calendar\AbstractCalendarView;

/**
 * View of an {@see \Daynum\Instant} as an Umm al-Qura (Saudi KACST) Hijri date.
 *
 * Use `$instant->hijri()` to construct; never `new` directly. The view will
 * throw {@see \Daynum\Exception\UmmAlQuraOutOfRangeException} when asked to
 * read components for a JDN outside the bundled UAQ table — use
 * `$instant->hijriCivil()` in that case instead.
 */
final class HijriUmmAlQuraView extends AbstractCalendarView
{
    public function calendar(): Calendar
    {
        return HijriUmmAlQuraCalendar::instance();
    }

    protected function defaultFormat(): string
    {
        return 'Y/m/d';
    }
}
