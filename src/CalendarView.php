<?php

declare(strict_types=1);

namespace Eram\Daynum;

/**
 * A view pairs an {@see CivilDateTime} with a specific calendar and locale, exposing
 * reading, formatting, and arithmetic in that calendar's terms.
 *
 * Views are immutable. Methods that appear to mutate (withLocale, addDays)
 * return a fresh view or CivilDateTime.
 */
interface CalendarView
{
    public function dateTime(): CivilDateTime;

    public function calendar(): Calendar;

    public function year(): int;

    public function month(): int;

    public function day(): int;

    public function hour(): int;

    public function minute(): int;

    public function second(): int;

    /** @return int 0..6, Sunday = 0 (PHP date('w') convention). */
    public function dayOfWeek(): int;

    /** @return int 1..7, Monday = 1 (ISO 8601 convention). */
    public function dayOfWeekIso(): int;

    public function dayOfYear(): int;

    /**
     * ISO 8601 week number within the calendar's own year (1–53).
     *
     * @throws \Eram\Daynum\Exception\WeekAtBoundaryException if this date falls
     *   within the first or last few days of MIN_YEAR / MAX_YEAR and the
     *   containing ISO week's Thursday lies outside the calendar's
     *   supported range.
     */
    public function weekOfYear(): int;

    /**
     * ISO 8601 week-based year — the year owning the ISO week of this
     * date's Thursday. Differs from {@see year()} by ±1 around Jan 1 /
     * Dec 31. Pair with {@see weekOfYear()} when emitting `Y-W`-style
     * identifiers that need to round-trip through ISO week arithmetic.
     *
     * @throws \Eram\Daynum\Exception\WeekAtBoundaryException on the same
     *   MIN_YEAR / MAX_YEAR boundary conditions as {@see weekOfYear()}.
     */
    public function weekBasedYear(): int;

    public function isLeapYear(): bool;

    public function daysInMonth(): int;

    public function daysInYear(): int;

    /**
     * Calendar-specific array of date/time components.
     *
     * @return array{year: int, month: int, day: int, hour: int, minute: int, second: int, tzLabel: ?string}
     */
    public function toArray(): array;

    public function format(string $pattern): string;

    public function withLocale(string $locale): static;

    public function withDigits(string $script): static;

    public function addDays(int $days): CivilDateTime;

    public function subDays(int $days): CivilDateTime;

    public function addMonths(int $months): CivilDateTime;

    public function subMonths(int $months): CivilDateTime;

    public function addYears(int $years): CivilDateTime;

    public function subYears(int $years): CivilDateTime;

    public function startOfMonth(): CivilDateTime;

    public function endOfMonth(): CivilDateTime;

    public function startOfYear(): CivilDateTime;

    public function endOfYear(): CivilDateTime;

    /**
     * First day of the week containing this date.
     *
     * @param WeekDay|int|null $weekStart ISO day-of-week of the first day of the
     *                                    week (1=Monday, 6=Saturday, 7=Sunday).
     *                                    Null uses the view locale's first day
     *                                    (en: Monday, fa: Saturday, ar: Sunday).
     */
    public function startOfWeek(WeekDay|int|null $weekStart = null): CivilDateTime;

    /**
     * Last day of the week containing this date.
     *
     * @param WeekDay|int|null $weekStart See {@see startOfWeek()}.
     */
    public function endOfWeek(WeekDay|int|null $weekStart = null): CivilDateTime;

    /** Day of the week as an enum. */
    public function weekDay(): WeekDay;

    /** Whether this date falls on the view locale's weekend (en: Sat–Sun, fa: Fri, ar: Fri–Sat). */
    public function isWeekend(): bool;

    /** Opposite of {@see isWeekend()}. */
    public function isWeekday(): bool;

    /**
     * Signed difference in whole calendar months between this date and another.
     *
     * A month is not counted until the same day-of-month is reached.
     */
    public function diffInMonths(CivilDateTime $other): int;

    /**
     * Signed difference in whole calendar years between this date and another.
     *
     * A year is not counted until the same day-of-month is reached.
     */
    public function diffInYears(CivilDateTime $other): int;

    /**
     * Human-readable difference from `$other`, e.g. "3 days ago" or
     * "in 2 hours", in the view's locale and digit script. `$other` plays
     * the role of "now": this date being earlier gives the past form.
     *
     * Uses the largest whole unit (year, month, week, day, hour, minute,
     * second). Years and months are counted in this view's calendar, so a
     * Jalali view counts Jalali months.
     */
    public function diffForHumans(CivilDateTime $other): string;

    /**
     * {@see diffForHumans()} against `CivilDateTime::now()` in this date's
     * timezone (PHP's default timezone when tzLabel is null).
     */
    public function ago(): string;

    /**
     * Whether this date's JDN falls within the calendar's supported range.
     */
    public function isInSupportedRange(): bool;

    /**
     * Try to parse the given text; return null instead of throwing.
     *
     * @see \Eram\Daynum\Calendar\AbstractCalendarView::parseExact()
     */
    public static function tryParseExact(
        string $text,
        string $format,
        ?string $tzLabel = null,
        ?string $locale = null,
    ): ?CivilDateTime;
}
