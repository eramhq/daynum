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

    public function weekOfYear(): int;

    public function isLeapYear(): bool;

    public function daysInMonth(): int;

    public function daysInYear(): int;

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
}
