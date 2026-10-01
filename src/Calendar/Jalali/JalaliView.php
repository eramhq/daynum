<?php

declare(strict_types=1);

namespace Eram\Daynum\Calendar\Jalali;

use Eram\Daynum\Calendar;
use Eram\Daynum\Calendar\AbstractCalendarView;
use Eram\Daynum\Season;

/**
 * View of an {@see \Eram\Daynum\CivilDateTime} as a Jalali (Shamsi / Solar Hijri) date.
 *
 * Use `$dateTime->jalali()` to construct; never `new` directly.
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

    /**
     * The season, which in the Jalali calendar is exactly the quarter:
     * Farvardin–Khordad is Spring, Tir–Shahrivar Summer, Mehr–Azar Autumn,
     * Dey–Esfand Winter. `startOfQuarter()` / `endOfQuarter()` give the
     * season's first and last day.
     */
    public function season(): Season
    {
        return Season::from($this->quarter());
    }

    /** Season name in this view's locale, e.g. "Spring" or "بهار". */
    public function seasonName(): string
    {
        return $this->locale->seasonName($this->season());
    }
}
