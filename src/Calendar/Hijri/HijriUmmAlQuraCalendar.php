<?php

declare(strict_types=1);

namespace Eram\Daynum\Calendar\Hijri;

use Eram\Daynum\Calendar;
use Eram\Daynum\Exception\InvalidDateException;
use Eram\Daynum\Exception\UmmAlQuraOutOfRangeException;
use Eram\Daynum\Internal\Ymd;

/**
 * Saudi Umm al-Qura (KACST) Hijri calendar.
 *
 * Unlike {@see HijriCivilCalendar}, Umm al-Qura is not arithmetic — its
 * month lengths are hand-curated per year based on astronomical observation
 * and new-moon visibility predictions published by KACST. Daynum bundles
 * ICU's `islamic-umalqura` data verbatim (see {@see Table} for the
 * generator-produced constants) and throws
 * {@see UmmAlQuraOutOfRangeException} for any date outside the bundled
 * range, because ICU's own silent fall-through to the arithmetic civil
 * calendar outside that window is exactly the kind of credibility hazard
 * Daynum exists to prevent.
 *
 * Unlike the civil variant, UAQ does not fix the leap day to Dhu al-Hijjah:
 * any month can have 29 or 30 days in any given year, and the leap test is
 * "did this year have 355 days?" rather than "is Dhu al-Hijjah 30 days?".
 *
 * ## Hot-path shape
 *
 * - `toJdn` is O(1): read `YEAR_STARTS[y]`, walk 12 bits to sum the month
 *   offset, add `d - 1`.
 * - `fromJdn` is O(log n + 12): binary search `YEAR_STARTS` for the
 *   containing year, then walk up to 12 months inside that year.
 * - `isLeapYear` is a popcount on a 12-bit int — four PHP integer ops.
 */
final class HijriUmmAlQuraCalendar implements Calendar
{
    private static ?self $instance = null;

    /**
     * Inclusive lower JDN bound, lazily computed once. The supported JDN
     * window depends on the bundled `Table.php`, so it can't be a class
     * constant — but we want to avoid recomputing it on every `fromJdn`.
     */
    private static ?int $minJdn = null;
    private static ?int $maxJdn = null;

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    public function toJdn(int $year, int $month, int $day): int
    {
        // Year is range-checked here (rather than via Ymd::validateYearMonth)
        // because UAQ uses its own exception type for the bounds violation.
        if ($year < Table::MIN_YEAR || $year > Table::MAX_YEAR) {
            throw UmmAlQuraOutOfRangeException::forYear($year, $month, $day);
        }
        if ($month < 1 || $month > 12) {
            throw InvalidDateException::forComponents(
                'hijri-umalqura',
                $year,
                $month,
                $day,
                'month must be in [1, 12]',
            );
        }

        $bits = Table::MONTH_LENGTHS[$year];
        $dim = ($bits & (1 << ($month - 1))) !== 0 ? 30 : 29;
        Ymd::validateDay('hijri-umalqura', $year, $month, $day, $dim);

        // Sum month lengths for months 1..(month-1).
        $offset = 0;
        for ($m = 0; $m < $month - 1; $m++) {
            $offset += 29 + (($bits >> $m) & 1);
        }

        return Table::YEAR_STARTS[$year] + $offset + ($day - 1);
    }

    public function fromJdn(int $jdn): array
    {
        self::$minJdn ??= Table::YEAR_STARTS[Table::MIN_YEAR];
        self::$maxJdn ??= Table::YEAR_STARTS[Table::MAX_YEAR]
            + self::yearLengthFromBits(Table::MONTH_LENGTHS[Table::MAX_YEAR]) - 1;

        if ($jdn < self::$minJdn || $jdn > self::$maxJdn) {
            throw UmmAlQuraOutOfRangeException::forJdn($jdn);
        }

        // Binary search YEAR_STARTS for the containing year.
        $min = Table::MIN_YEAR;
        $max = Table::MAX_YEAR;
        while ($min < $max) {
            $mid = intdiv($min + $max + 1, 2);
            if (Table::YEAR_STARTS[$mid] <= $jdn) {
                $min = $mid;
            } else {
                $max = $mid - 1;
            }
        }
        $year = $min;

        // Walk months inside $year.
        $remaining = $jdn - Table::YEAR_STARTS[$year];
        $bits = Table::MONTH_LENGTHS[$year];
        $month = 1;
        for ($m = 0; $m < 12; $m++) {
            $dim = 29 + (($bits >> $m) & 1);
            if ($remaining < $dim) {
                $month = $m + 1;
                break;
            }
            $remaining -= $dim;
            $month = $m + 2;
        }

        return [$year, $month, $remaining + 1];
    }

    public function isLeapYear(int $year): bool
    {
        if ($year < Table::MIN_YEAR || $year > Table::MAX_YEAR) {
            throw UmmAlQuraOutOfRangeException::forYear($year, 1, 1);
        }
        // Year length = 348 + popcount(bits). Leap ⇔ year length is 355.
        return self::yearLengthFromBits(Table::MONTH_LENGTHS[$year]) === 355;
    }

    public function daysInMonth(int $year, int $month): int
    {
        if ($year < Table::MIN_YEAR || $year > Table::MAX_YEAR) {
            throw UmmAlQuraOutOfRangeException::forYear($year, $month, 1);
        }
        if ($month < 1 || $month > 12) {
            throw new InvalidDateException(
                "Invalid Hijri month {$month} (expected 1..12)."
            );
        }
        return (Table::MONTH_LENGTHS[$year] & (1 << ($month - 1))) !== 0 ? 30 : 29;
    }

    public function dayOfYear(int $year, int $month, int $day): int
    {
        // Throw out-of-range rather than silently fall through — see class docblock.
        if ($year < Table::MIN_YEAR || $year > Table::MAX_YEAR) {
            throw UmmAlQuraOutOfRangeException::forYear($year, $month, $day);
        }
        $bits = Table::MONTH_LENGTHS[$year];
        $offset = 0;
        for ($m = 0; $m < $month - 1; $m++) {
            $offset += 29 + (($bits >> $m) & 1);
        }
        return $offset + $day;
    }

    public function monthsInYear(int $year): int
    {
        return 12;
    }

    public function name(): string
    {
        return 'hijri-umalqura';
    }

    public function localeFamily(): string
    {
        return 'hijri';
    }

    public function supportedRange(): array
    {
        return [
            Table::YEAR_STARTS[Table::MIN_YEAR],
            Table::YEAR_STARTS[Table::MAX_YEAR]
                + self::yearLengthFromBits(Table::MONTH_LENGTHS[Table::MAX_YEAR]) - 1,
        ];
    }

    public function supportsYear(int $year): bool
    {
        return $year >= Table::MIN_YEAR && $year <= Table::MAX_YEAR;
    }

    /**
     * Total days in a year, given its bit-packed month-length word.
     *
     * Year length is `29*12 + popcount(bits) = 348 + popcount`. UAQ years
     * always have 354 or 355 days, so the popcount is always 6 or 7.
     */
    private static function yearLengthFromBits(int $bits): int
    {
        $popcount = 0;
        for ($m = 0; $m < 12; $m++) {
            $popcount += ($bits >> $m) & 1;
        }
        return 348 + $popcount;
    }
}
