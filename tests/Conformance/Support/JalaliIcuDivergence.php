<?php

declare(strict_types=1);

namespace Daynum\Tests\Conformance\Support;

/**
 * Canonical list of JDN ranges where Daynum's Birashk Jalali disagrees with
 * ICU's Borkowski Persian calendar.
 *
 * See {@see \Daynum\Calendar\Jalali\JalaliCalendar} for the full explanation.
 * Every conformance test that iterates the Jalali oracle fixture must filter
 * these windows through {@see self::contains()} so fixtures regenerated from
 * a newer ICU stay authoritative without masking genuine regressions.
 *
 * If ICU ever adds or removes a window the RANGES list must be updated in
 * exactly one place — here.
 */
final class JalaliIcuDivergence
{
    /**
     * Inclusive [lo, hi] JDN ranges. Total: 79 + 3 * 366 = 1177 days.
     *
     * @var list<array{int,int}>
     */
    public const RANGES = [
        [2341973, 2342051], //  79 days, straddling Nowruz 1078 AP / 1700 CE
        [2377845, 2378210], // 366 days, Nowruz 1177 AP / 1798 CE
        [2496914, 2497279], // 366 days, Nowruz 1503 AP / 2124 CE
        [2533073, 2533438], // 366 days, Nowruz 1602 AP / 2223 CE
    ];

    public const TOTAL_DAYS = 1177;

    public static function contains(int $jdn): bool
    {
        foreach (self::RANGES as [$lo, $hi]) {
            if ($jdn >= $lo && $jdn <= $hi) {
                return true;
            }
        }
        return false;
    }
}
