<?php

declare(strict_types=1);

namespace Daynum\Locale;

use InvalidArgumentException;

/**
 * Table-driven {@see LocaleData} base.
 *
 * Concrete locales supply four arrays (months / short months / weekdays /
 * short weekdays) keyed first by calendar name, and this class handles the
 * lookups and the error paths. This keeps new locales pure data declarations
 * and ensures every locale uses identical error messages.
 */
abstract class AbstractTableLocale implements LocaleData
{
    /**
     * Full month names, keyed by calendar name then 1-indexed month.
     *
     * @return array<string, array<int, string>>
     */
    abstract protected function longMonthTable(): array;

    /**
     * Short month names. Locales that lack abbreviations may return the
     * same table as {@see longMonthTable()}.
     *
     * @return array<string, array<int, string>>
     */
    abstract protected function shortMonthTable(): array;

    /**
     * Weekday names indexed by PHP day-of-week (Sunday = 0..Saturday = 6).
     *
     * @return array<int, string>
     */
    abstract protected function longWeekdayTable(): array;

    /**
     * @return array<int, string>
     */
    abstract protected function shortWeekdayTable(): array;

    public function monthName(string $calendar, int $month): string
    {
        return self::lookupMonth($this->longMonthTable(), $calendar, $month);
    }

    public function monthNameShort(string $calendar, int $month): string
    {
        return self::lookupMonth($this->shortMonthTable(), $calendar, $month);
    }

    public function weekdayName(int $dayOfWeek): string
    {
        return self::lookupWeekday($this->longWeekdayTable(), $dayOfWeek);
    }

    public function weekdayNameShort(int $dayOfWeek): string
    {
        return self::lookupWeekday($this->shortWeekdayTable(), $dayOfWeek);
    }

    /**
     * @param array<string, array<int, string>> $table
     */
    private static function lookupMonth(array $table, string $calendar, int $month): string
    {
        if (!isset($table[$calendar])) {
            throw new InvalidArgumentException("Unknown calendar: {$calendar}");
        }
        return $table[$calendar][$month]
            ?? throw new InvalidArgumentException("Invalid {$calendar} month: {$month}");
    }

    /**
     * @param array<int, string> $table
     */
    private static function lookupWeekday(array $table, int $dayOfWeek): string
    {
        return $table[$dayOfWeek]
            ?? throw new InvalidArgumentException("Invalid day of week (expected 0..6): {$dayOfWeek}");
    }
}
