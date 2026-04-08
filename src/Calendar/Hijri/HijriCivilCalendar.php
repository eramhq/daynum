<?php

declare(strict_types=1);

namespace Daynum\Calendar\Hijri;

use Daynum\Calendar;
use Daynum\Exception\InvalidDateException;
use Daynum\Internal\IntMath;
use Daynum\Internal\Ymd;

/**
 * Tabular ("civil") Islamic calendar — the deterministic 30-year arithmetic
 * cycle with the *year-16 leap* variant, identical to ICU's `islamic-civil`
 * and Reingold–Dershowitz's "Arithmetic Islamic" calendar.
 *
 * ## Why this variant
 *
 * Islamic calendars come in several flavors. Daynum ships the one that
 * tools, libraries, and academic references have converged on:
 *
 * - **Year-16 leap (`islamic-civil`, ICU, Reingold–Dershowitz, Joda-Time)**
 *   — leap years inside each 30-cycle at positions {2, 5, 7, 10, 13, 16,
 *   18, 21, 24, 26, 29}. This is the variant Daynum implements.
 * - The "Kuwaiti" year-15 leap variant is historical only and not supported.
 * - `islamic-tbla` uses JDN epoch 1948439 (Thursday) instead of 1948440
 *   (Friday); not supported.
 * - `islamic` (observational / astronomical) is non-deterministic and will
 *   never ship in v1.
 *
 * The epoch is **JDN 1948440**, corresponding to Friday 16 July 622 CE
 * (Julian). The leap test is `(11*y + 14) mod 30 < 11`, which selects the
 * eleven canonical leap positions inside every 30-year cycle.
 *
 * Forward conversion (closed-form):
 *
 *     JDN = EPOCH
 *         + 354 * (y - 1)             // days in complete prior common years
 *         + floor((11*y + 3) / 30)     // cumulative leap days before y
 *         + 29 * (m - 1) + floor(m/2)  // days in prior months (30/29 alternating)
 *         + d - 1
 *
 * Reverse conversion derives `y` from a tight closed form and then walks
 * twelve months at most to locate `(m, d)`, so `fromJdn` is O(1).
 *
 * References:
 *   - Nachum Dershowitz & Edward M. Reingold, *Calendrical Calculations*
 *     (4th ed., Cambridge University Press, 2018), "Arithmetic Islamic
 *     Calendar" chapter.
 *   - ICU `IslamicCalendar.java` (`CIVIL` civil type), Unicode ICU license
 *     (BSD-style).
 */
final class HijriCivilCalendar implements Calendar
{
    /** Julian Day Number of AH 1-1-1 (16 July 622 CE Julian). */
    public const EPOCH = 1948440;

    public const MIN_YEAR = 1;

    /**
     * Upper bound cited by Reingold–Dershowitz as the largest year at which
     * the closed-form arithmetic stays well within machine integer bounds
     * on the oldest platforms the references cover. PHP is 64-bit so we
     * could go much higher; 9666 is kept so ports to narrower targets stay
     * valid.
     */
    public const MAX_YEAR = 9666;

    private static ?self $instance = null;

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    public function toJdn(int $year, int $month, int $day): int
    {
        Ymd::validateYearMonth('hijri-civil', $year, $month, $day, self::MIN_YEAR, self::MAX_YEAR, 12);
        Ymd::validateDay('hijri-civil', $year, $month, $day, $this->daysInMonth($year, $month));

        // Days-into-prior-months closed form: 29*(m-1) + floor(m/2) is the
        // cumulative-day count under the alternating 30/29 pattern.
        $priorMonthsDays = 29 * ($month - 1) + IntMath::floorDiv($month, 2);

        return $this->yearStartJdn($year) + $priorMonthsDays + ($day - 1);
    }

    public function fromJdn(int $jdn): array
    {
        // Closed form for year from the Reingold–Dershowitz derivation:
        // y = floor((30 * (JDN - EPOCH) + 10646) / 10631).
        // This lands on the correct year across the whole AH 1..9666 range.
        $daysSinceEpoch = $jdn - self::EPOCH;
        $year = IntMath::floorDiv(30 * $daysSinceEpoch + 10646, 10631);

        // Walk months 1..12 within the computed year. At most 12 steps.
        $remaining = $jdn - $this->yearStartJdn($year);
        $month = 1;
        while ($month < 12) {
            $dim = $this->daysInMonth($year, $month);
            if ($remaining < $dim) {
                break;
            }
            $remaining -= $dim;
            $month++;
        }

        return [$year, $month, $remaining + 1];
    }

    public function isLeapYear(int $year): bool
    {
        // (11y + 14) mod 30 < 11 → positions {2, 5, 7, 10, 13, 16, 18, 21,
        // 24, 26, 29} inside every 30-cycle. Year is non-negative within
        // the supported range so plain `%` is safe.
        return (11 * $year + 14) % 30 < 11;
    }

    /**
     * JDN of Muharram 1 of `$year` — the inner half of the closed-form
     * `toJdn`, factored out so `fromJdn`'s hot path can call it without
     * re-running the validation prelude.
     */
    private function yearStartJdn(int $year): int
    {
        return self::EPOCH
            + 354 * ($year - 1)
            + IntMath::floorDiv(11 * $year + 3, 30);
    }

    public function daysInMonth(int $year, int $month): int
    {
        if ($month < 1 || $month > 12) {
            throw new InvalidDateException(
                "Invalid Hijri month {$month} (expected 1..12)."
            );
        }
        // Odd months 1,3,5,7,9,11 → 30 days; even months 2,4,6,8,10 → 29
        // days; month 12 (Dhu al-Hijjah) → 30 in leap years, 29 otherwise.
        if ($month === 12) {
            return $this->isLeapYear($year) ? 30 : 29;
        }
        return ($month % 2 === 1) ? 30 : 29;
    }

    public function dayOfYear(int $year, int $month, int $day): int
    {
        // Mirrors the cumulative-day expression in toJdn.
        return 29 * ($month - 1) + IntMath::floorDiv($month, 2) + $day;
    }

    public function monthsInYear(int $year): int
    {
        return 12;
    }

    public function name(): string
    {
        return 'hijri-civil';
    }

    public function localeFamily(): string
    {
        return 'hijri';
    }

    public function supportedRange(): array
    {
        return [
            $this->toJdn(self::MIN_YEAR, 1, 1),
            $this->toJdn(self::MAX_YEAR, 12, $this->daysInMonth(self::MAX_YEAR, 12)),
        ];
    }
}
