<?php

declare(strict_types=1);

namespace Daynum;

/**
 * A calendar system: the pure math of mapping between a (year, month, day) triple
 * and a Julian Day Number (JDN).
 *
 * Implementations are pure and stateless. All conversions must go through JDN so
 * that composing any two calendars is as trivial as composing two functions. The
 * JDN is the interlingua for the entire library.
 *
 * Implementations MUST NOT touch time-of-day or timezone data; those live on
 * {@see Instant} and flow through untouched.
 */
interface Calendar
{
    /**
     * Convert a calendar date to its Julian Day Number.
     *
     * @throws \Daynum\Exception\InvalidDateException if the components are invalid
     *         for this calendar (e.g., February 30, month 13, year outside the
     *         calendar's supported range).
     */
    public function toJdn(int $year, int $month, int $day): int;

    /**
     * Convert a Julian Day Number to a calendar date.
     *
     * @return array{0: int, 1: int, 2: int} Tuple of [year, month, day]. Month is
     *         1-indexed (January = 1), day is 1-indexed.
     */
    public function fromJdn(int $jdn): array;

    public function isLeapYear(int $year): bool;

    public function daysInMonth(int $year, int $month): int;

    /**
     * 1-indexed day of the calendar year. Implementations may assume the
     * components are valid for this calendar; callers are responsible for
     * pre-validation. Implementations with bundled-data ranges may still
     * throw their own out-of-range errors.
     */
    public function dayOfYear(int $year, int $month, int $day): int;

    /**
     * Number of months in the given year. 12 for all calendars v1 ships; 13 for
     * the Hebrew calendar in a leap year (future).
     */
    public function monthsInYear(int $year): int;

    /**
     * Stable identifier for this calendar.
     *
     * @return string e.g. "gregorian", "jalali", "hijri-umalqura", "hijri-civil"
     */
    public function name(): string;

    /**
     * Identifier used for locale month-name lookup.
     *
     * Distinct from {@see name()}: multiple calendars can share a family when
     * they use the same month names. Both `hijri-civil` and `hijri-umalqura`
     * return `'hijri'` because they differ only in leap rules and month
     * lengths, not in what the months are called.
     *
     * @return string e.g. "gregorian", "jalali", "hijri"
     */
    public function localeFamily(): string;

    /**
     * Inclusive [min, max] JDN range this calendar can convert to/from.
     *
     * @return array{0: int, 1: int}
     */
    public function supportedRange(): array;
}
