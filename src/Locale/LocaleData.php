<?php

declare(strict_types=1);

namespace Eram\Daynum\Locale;

use Eram\Daynum\WeekDay;

/**
 * Locale-specific names and strings used by the token formatter.
 *
 * Month names are looked up by *locale family*, not by individual calendar
 * identifier, because several calendars can share a naming scheme. The
 * tabular Hijri civil calendar and the Saudi Umm al-Qura calendar both use
 * the family `hijri`, so a single Hijri month table serves both.
 */
interface LocaleData
{
    /** Units accepted by {@see relativeTime()}, smallest first. */
    public const RELATIVE_TIME_UNITS = ['second', 'minute', 'hour', 'day', 'week', 'month', 'year'];

    /**
     * BCP 47 language tag such as "en" or "fa".
     */
    public function tag(): string;

    /**
     * Full month name for a given calendar family.
     *
     * @param string $family locale family from `Calendar::localeFamily()`:
     *                       `"gregorian"`, `"jalali"`, `"hijri"`, ...
     * @param int $month     1-indexed month number
     */
    public function monthName(string $family, int $month): string;

    /**
     * Abbreviated month name (e.g., "Jan").
     */
    public function monthNameShort(string $family, int $month): string;

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

    /**
     * English-style ordinal suffix for the `S` token (`st`, `nd`, `rd`,
     * `th`). Locales that have no native ordinal-suffix convention return an
     * empty string, so that patterns like `jS F Y` render cleanly across
     * languages instead of leaving broken English residue inside
     * non-Latin-script output.
     *
     * @param int $day 1-indexed day of month
     */
    public function ordinalSuffix(int $day): string;

    /**
     * First day of the week in this locale's region, used by
     * `startOfWeek()` / `endOfWeek()` when no explicit start is given.
     */
    public function firstDayOfWeek(): WeekDay;

    /**
     * Days of the weekend in this locale's region, used by `isWeekend()`.
     *
     * @return list<WeekDay>
     */
    public function weekendDays(): array;

    /**
     * A relative-time phrase such as "3 days ago" or "in 2 hours", with
     * Latin digits (views transliterate them to their digit script).
     * Matches `Intl.RelativeTimeFormat` with `numeric: 'always'`, minus
     * digit grouping for values of 1000 and above.
     *
     * @param int    $value  non-negative count
     * @param string $unit   one of {@see RELATIVE_TIME_UNITS}
     * @param bool   $future true for "in 3 days", false for "3 days ago"
     *
     * @throws \Eram\Daynum\Exception\InvalidArgumentException on an unknown
     *         unit or a negative value
     */
    public function relativeTime(int $value, string $unit, bool $future): string;

    /**
     * The word used instead of "0 seconds ago" when two values are equal,
     * e.g. "now". Matches `Intl.RelativeTimeFormat` with `numeric: 'auto'`.
     */
    public function relativeTimeNow(): string;
}
