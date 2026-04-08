<?php

declare(strict_types=1);

namespace Daynum\Calendar\Jalali;

use Daynum\Calendar;
use Daynum\Calendar\Gregorian\GregorianCalendar;
use Daynum\Exception\InvalidDateException;

/**
 * Jalali (Shamsi / Solar Hijri) calendar using Ahmad Birashk's 33-year
 * arithmetic cycle.
 *
 * The core Birashk logic (`jalCal`) — the break-point table and the offsetting
 * math that determines the Gregorian date of Farvardin 1 of any Jalali year —
 * is a faithful port of the `jalaali-js` algorithm
 * (https://github.com/jalaali/jalaali-js), the basis for `morilog/jalali` and
 * `date-fns-jalali`. All three are MIT licensed. Choosing Birashk is a
 * deliberate ecosystem choice: it's the algorithm every migrating PHP/JS
 * developer already knows and tests against.
 *
 * ## Birashk vs. ICU divergence
 *
 * Birashk is NOT identical to ICU's `persian` calendar. ICU uses Borkowski's
 * arithmetic algorithm, which differs from Birashk at a handful of year
 * boundaries over centuries. Within the fixture range 1700–2300 Gregorian, the
 * two algorithms disagree on only four contiguous JDN ranges, each centered
 * on a Nowruz where the two assign the leap day to different years:
 *
 *   | Birashk says leap | ICU says leap | Gregorian window  | Daynum divergence |
 *   |-------------------|---------------|-------------------|-------------------|
 *   | (neither)         | 1078 AP       | 1700-01-01..03-19 |  79 days          |
 *   | 1176 AP           | 1177 AP       | 1797-03-21..1798  | 366 days          |
 *   | 1502 AP           | 1503 AP       | 2123-03-21..2124  | 366 days          |
 *   | 1601 AP           | 1602 AP       | 2222-03-21..2223  | 366 days          |
 *
 * Outside these four windows Daynum and ICU agree byte-for-byte. Within them,
 * Daynum is one day off ICU. The IcuConformanceTest allow-list documents the
 * exact JDN ranges; any disagreement outside those ranges is a regression.
 *
 * This divergence is inherent to the choice of algorithm, not a bug. Daynum
 * prioritizes compatibility with the dominant PHP/JS Jalali ecosystem
 * (morilog/jalali, jalaali-js, date-fns-jalali) over strict ICU conformance.
 *
 * References:
 *   jalaali/jalaali-js — src/jalaali.js (`jalCal`, `toJalaali`, `toGregorian`)
 *   morilog/jalali     — src/CalendarUtils.php
 *   Ahmad Birashk, "A New Survey of the Persian Calendar" (1993)
 *   ICU PersianCalendar — http://source.icu-project.org/repos/icu/
 */
final class JalaliCalendar implements Calendar
{
    public const MIN_YEAR = 1;
    public const MAX_YEAR = 3177;

    /**
     * Birashk break-points: Jalali years at which the 33-year cycle resets.
     * The table is ported verbatim from jalaali-js and MUST NOT be modified
     * without a complete conformance re-run.
     *
     * @var list<int>
     */
    private const BREAKS = [
        -61, 9, 38, 199, 426, 686, 756, 818, 1111, 1181,
        1210, 1635, 2060, 2097, 2192, 2262, 2324, 2394, 2456, 3178,
    ];

    private static ?self $instance = null;

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    public function toJdn(int $year, int $month, int $day): int
    {
        if ($year < self::MIN_YEAR || $year > self::MAX_YEAR) {
            throw InvalidDateException::forComponents(
                'jalali',
                $year,
                $month,
                $day,
                sprintf('year must be in [%d, %d]', self::MIN_YEAR, self::MAX_YEAR),
            );
        }
        if ($month < 1 || $month > 12) {
            throw InvalidDateException::forComponents(
                'jalali',
                $year,
                $month,
                $day,
                'month must be in [1, 12]',
            );
        }
        // Fold the leap-year lookup and the day-range check into a single
        // jalCal call so `toJdn` hits the Birashk tables once, not twice.
        $r = $this->jalCal($year);
        $dim = $month <= 6 ? 31 : ($month <= 11 ? 30 : ($r['leap'] === 0 ? 30 : 29));
        if ($day < 1 || $day > $dim) {
            throw InvalidDateException::forComponents(
                'jalali',
                $year,
                $month,
                $day,
                sprintf('day must be in [1, %d] for that month', $dim),
            );
        }

        $farvardin1Jdn = GregorianCalendar::instance()->toJdn($r['gy'], 3, $r['march']);

        // Days elapsed within the Jalali year:
        //   * months 1..6 are 31 days each                → (m-1)*31
        //   * months 7..12 are 30 days each               → 6*31 + (m-7)*30
        //   The closed form below collapses both cases.
        $dayOfYear = ($month - 1) * 31 - intdiv($month, 7) * ($month - 7) + $day - 1;

        return $farvardin1Jdn + $dayOfYear;
    }

    public function fromJdn(int $jdn): array
    {
        // Use the Gregorian year of the given JDN as a starting guess; Farvardin 1
        // always falls in March, so the Gregorian year of any Jalali date is either
        // $gy (Farvardin..Dey) or $gy - 1 (rolled-back case for early Farvardin).
        [$gy, , ] = GregorianCalendar::instance()->fromJdn($jdn);
        $jy = $gy - 621;
        $r = $this->jalCal($jy);
        $farvardin1Jdn = GregorianCalendar::instance()->toJdn($gy, 3, $r['march']);
        $k = $jdn - $farvardin1Jdn;

        if ($k >= 0) {
            if ($k <= 185) {
                // Farvardin..Shahrivar (months 1..6) — all 31 days
                $jm = 1 + intdiv($k, 31);
                $jd = ($k % 31) + 1;
                return [$jy, $jm, $jd];
            }
            // Mehr..Esfand (months 7..12) — all 30 days plus optional leap day
            $k -= 186;
        } else {
            // We guessed one year too high; roll back.
            $jy--;
            $k += 179;
            if ($r['leap'] === 1) {
                // r.leap == 1 means "1 year has passed since the last leap year in
                // the year we rolled FROM" — i.e. the year we rolled INTO was a
                // leap year, so Esfand had 30 days, not 29.
                $k++;
            }
        }

        $jm = 7 + intdiv($k, 30);
        $jd = ($k % 30) + 1;
        return [$jy, $jm, $jd];
    }

    public function isLeapYear(int $year): bool
    {
        return $this->jalCal($year)['leap'] === 0;
    }

    public function daysInMonth(int $year, int $month): int
    {
        if ($month < 1 || $month > 12) {
            throw new InvalidDateException("Invalid Jalali month {$month} (expected 1..12).");
        }
        if ($month <= 6) {
            return 31;
        }
        if ($month <= 11) {
            return 30;
        }
        return $this->isLeapYear($year) ? 30 : 29;
    }

    public function monthsInYear(int $year): int
    {
        return 12;
    }

    public function name(): string
    {
        return 'jalali';
    }

    public function supportedRange(): array
    {
        return [
            $this->toJdn(self::MIN_YEAR, 1, 1),
            $this->toJdn(self::MAX_YEAR, 12, $this->daysInMonth(self::MAX_YEAR, 12)),
        ];
    }

    /**
     * Determine, for a given Jalali year, the Gregorian year + day-of-March
     * on which Farvardin 1 falls, and a leap-year indicator.
     *
     * The `leap` field is the number of years since the previous leap year;
     * 0 means this year is itself a leap year, 1 means the preceding year was,
     * and so on up to 4.
     *
     * Ported from jalaali-js @ `src/jalaali.js`.
     *
     * @return array{leap:int, gy:int, march:int}
     */
    private function jalCal(int $jy): array
    {
        $breaks = self::BREAKS;
        $bl = count($breaks);
        $gy = $jy + 621;
        $leapJ = -14;
        $jp = $breaks[0];

        $jump = 0;
        for ($i = 1; $i < $bl; $i++) {
            $jm = $breaks[$i];
            $jump = $jm - $jp;
            if ($jy < $jm) {
                break;
            }
            $leapJ += intdiv($jump, 33) * 8 + intdiv($jump % 33, 4);
            $jp = $jm;
        }

        $n = $jy - $jp;
        $leapJ += intdiv($n, 33) * 8 + intdiv(($n % 33) + 3, 4);
        if (($jump % 33) === 4 && ($jump - $n) === 4) {
            $leapJ++;
        }

        $leapG = intdiv($gy, 4)
            - intdiv((intdiv($gy, 100) + 1) * 3, 4)
            - 150;

        $march = 20 + $leapJ - $leapG;

        if (($jump - $n) < 6) {
            $n = $n - $jump + intdiv($jump + 4, 33) * 33;
        }

        $leap = ((($n + 1) % 33) - 1) % 4;
        if ($leap === -1) {
            $leap = 4;
        }

        return ['leap' => $leap, 'gy' => $gy, 'march' => $march];
    }
}
