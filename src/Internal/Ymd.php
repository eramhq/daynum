<?php

declare(strict_types=1);

namespace Daynum\Internal;

use Daynum\Exception\InvalidDateException;

/**
 * @internal
 *
 * Shared (year, month, day) validation used by every calendar's `toJdn`.
 *
 * Splitting validation into two calls (year/month first, then day) lets the
 * caller compute days-in-month between them — important for calendars like
 * Jalali whose `daysInMonth` is expensive (it triggers the Birashk table
 * walk) and where `toJdn` already needs the result for its own arithmetic.
 * Both halves are plain function calls, so the validation prelude allocates
 * nothing on the hot path.
 */
final class Ymd
{
    public static function validateYearMonth(
        string $calendarName,
        int $year,
        int $month,
        int $day,
        int $minYear,
        int $maxYear,
        int $monthsInYear,
    ): void {
        if ($year < $minYear || $year > $maxYear) {
            throw InvalidDateException::forComponents(
                $calendarName,
                $year,
                $month,
                $day,
                sprintf('year must be in [%d, %d]', $minYear, $maxYear),
            );
        }
        if ($month < 1 || $month > $monthsInYear) {
            throw InvalidDateException::forComponents(
                $calendarName,
                $year,
                $month,
                $day,
                sprintf('month must be in [1, %d]', $monthsInYear),
            );
        }
    }

    public static function validateDay(
        string $calendarName,
        int $year,
        int $month,
        int $day,
        int $daysInMonth,
    ): void {
        if ($day < 1 || $day > $daysInMonth) {
            throw InvalidDateException::forComponents(
                $calendarName,
                $year,
                $month,
                $day,
                sprintf('day must be in [1, %d] for that month', $daysInMonth),
            );
        }
    }
}
