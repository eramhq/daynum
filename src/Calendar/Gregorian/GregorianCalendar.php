<?php

declare(strict_types=1);

namespace Daynum\Calendar\Gregorian;

use Daynum\Calendar;
use Daynum\Exception\InvalidDateException;
use Daynum\Internal\IntMath;
use Daynum\Internal\Ymd;

/**
 * Proleptic Gregorian calendar — no Julian cutover, year 0 exists, negative
 * years are permitted.
 *
 * JDN conversion uses the Fliegel–Van Flandern algorithm (1968/1990), the
 * standard closed-form formula, cross-checked against Reingold–Dershowitz's
 * "Calendrical Calculations" (4th ed., 2018).
 *
 * This calendar is stateless; use {@see instance()} to avoid allocation.
 */
final class GregorianCalendar implements Calendar
{
    /** Inclusive year range for construction. */
    public const MIN_YEAR = -9999;
    public const MAX_YEAR = 9999;

    private static ?self $instance = null;

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    public function toJdn(int $year, int $month, int $day): int
    {
        Ymd::validateYearMonth('gregorian', $year, $month, $day, self::MIN_YEAR, self::MAX_YEAR, 12);
        Ymd::validateDay('gregorian', $year, $month, $day, $this->daysInMonth($year, $month));

        $a = IntMath::floorDiv(14 - $month, 12);
        $y = $year + 4800 - $a;
        $m = $month + 12 * $a - 3;

        return $day
            + IntMath::floorDiv(153 * $m + 2, 5)
            + 365 * $y
            + IntMath::floorDiv($y, 4)
            - IntMath::floorDiv($y, 100)
            + IntMath::floorDiv($y, 400)
            - 32045;
    }

    public function fromJdn(int $jdn): array
    {
        $a = $jdn + 32044;
        $b = IntMath::floorDiv(4 * $a + 3, 146097);
        $c = $a - IntMath::floorDiv(146097 * $b, 4);
        $d = IntMath::floorDiv(4 * $c + 3, 1461);
        $e = $c - IntMath::floorDiv(1461 * $d, 4);
        $m = IntMath::floorDiv(5 * $e + 2, 153);

        $day = $e - IntMath::floorDiv(153 * $m + 2, 5) + 1;
        $month = $m + 3 - 12 * IntMath::floorDiv($m, 10);
        $year = 100 * $b + $d - 4800 + IntMath::floorDiv($m, 10);

        return [$year, $month, $day];
    }

    public function isLeapYear(int $year): bool
    {
        if ($year % 4 !== 0) {
            return false;
        }
        if ($year % 100 !== 0) {
            return true;
        }
        return $year % 400 === 0;
    }

    public function daysInMonth(int $year, int $month): int
    {
        static $days = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
        if ($month < 1 || $month > 12) {
            throw new InvalidDateException("Invalid Gregorian month {$month} (expected 1..12).");
        }
        if ($month === 2 && $this->isLeapYear($year)) {
            return 29;
        }
        return $days[$month - 1];
    }

    /**
     * 1-indexed day of year, proleptic Gregorian. No validation: callers
     * pass an already-decomposed `(year, month, day)` tuple.
     */
    public function dayOfYear(int $year, int $month, int $day): int
    {
        static $cum = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
        $d = $cum[$month - 1] + $day;
        if ($month > 2 && $this->isLeapYear($year)) {
            $d++;
        }
        return $d;
    }

    public function monthsInYear(int $year): int
    {
        return 12;
    }

    public function name(): string
    {
        return 'gregorian';
    }

    public function localeFamily(): string
    {
        return 'gregorian';
    }

    public function supportedRange(): array
    {
        return [
            $this->toJdn(self::MIN_YEAR, 1, 1),
            $this->toJdn(self::MAX_YEAR, 12, 31),
        ];
    }

    public function supportsYear(int $year): bool
    {
        return $year >= self::MIN_YEAR && $year <= self::MAX_YEAR;
    }
}
