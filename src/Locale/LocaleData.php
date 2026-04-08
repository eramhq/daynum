<?php

declare(strict_types=1);

namespace Daynum\Locale;

/**
 * Locale-specific names and strings used by the token formatter.
 *
 * A locale is defined per-calendar because month names are calendar-specific:
 * the Gregorian "January" and the Jalali "Farvardin" are different concepts
 * even in the same human language.
 */
interface LocaleData
{
    /**
     * BCP 47 language tag such as "en" or "fa".
     */
    public function tag(): string;

    /**
     * Full month name for a given calendar.
     *
     * @param string $calendar "gregorian" or "jalali" etc.
     * @param int $month 1-indexed month number
     */
    public function monthName(string $calendar, int $month): string;

    /**
     * Abbreviated month name (e.g., "Jan").
     */
    public function monthNameShort(string $calendar, int $month): string;

    /**
     * Full weekday name.
     *
     * @param int $dayOfWeek 0..6, Sunday = 0 (PHP convention)
     */
    public function weekdayName(int $dayOfWeek): string;

    /**
     * Abbreviated weekday name (e.g., "Sun").
     */
    public function weekdayNameShort(int $dayOfWeek): string;

    /**
     * AM/PM indicator used by the `a` and `A` tokens.
     *
     * @param bool $isPm
     * @param bool $uppercase
     */
    public function meridiem(bool $isPm, bool $uppercase): string;
}
