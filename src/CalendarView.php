<?php

declare(strict_types=1);

namespace Daynum;

/**
 * A view pairs an {@see Instant} with a specific calendar and locale, exposing
 * reading, formatting, and arithmetic in that calendar's terms.
 *
 * Views are immutable. Methods that appear to mutate (withLocale, addDays)
 * return a fresh view or Instant.
 */
interface CalendarView
{
    public function instant(): Instant;

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
     * @throws \Daynum\Exception\WeekAtBoundaryException if this date falls
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
     * @throws \Daynum\Exception\WeekAtBoundaryException on the same
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

    public function addDays(int $days): Instant;

    public function subDays(int $days): Instant;

    public function addMonths(int $months): Instant;

    public function subMonths(int $months): Instant;

    public function addYears(int $years): Instant;

    public function subYears(int $years): Instant;

    public function startOfMonth(): Instant;

    public function endOfMonth(): Instant;

    public function startOfYear(): Instant;

    public function endOfYear(): Instant;

    public function diffInMonths(Instant $other): int;

    /**
     * Try to parse the given text; return null instead of throwing.
     *
     * @see \Daynum\Calendar\AbstractCalendarView::parseExact()
     */
    public static function tryParseExact(string $text, string $format, ?string $tzLabel = null): ?Instant;
}
