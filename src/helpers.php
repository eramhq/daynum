<?php

declare(strict_types=1);

/**
 * Opt-in global helper functions mirroring the morilog/jalali API style.
 *
 * This file is NOT autoloaded by default. To enable these helpers, add the
 * following to your own project's `composer.json`:
 *
 *     "autoload": {
 *         "files": ["vendor/eram/daynum/src/helpers.php"]
 *     }
 *
 * Each helper is wrapped in `function_exists` so Daynum will never silently
 * override a name the host application has already defined.
 */

use Eram\Daynum\Instant;

if (!function_exists('gdate')) {
    function gdate(int $year, int $month, int $day, int $hour = 0, int $minute = 0, int $second = 0, ?string $tz = null): Instant
    {
        return Instant::fromGregorian($year, $month, $day, $hour, $minute, $second, $tz);
    }
}

if (!function_exists('jdate')) {
    function jdate(int $year, int $month, int $day, int $hour = 0, int $minute = 0, int $second = 0, ?string $tz = null): Instant
    {
        return Instant::fromJalali($year, $month, $day, $hour, $minute, $second, $tz);
    }
}

if (!function_exists('hdate')) {
    /**
     * Construct an {@see Instant} from a Saudi Umm al-Qura (KACST) Hijri date.
     *
     * Mirrors the `gdate` / `jdate` helpers and defaults to the UAQ
     * calendar to match `Instant::fromHijri`. If the year is outside the
     * bundled table range, this throws
     * {@see \Eram\Daynum\Exception\UmmAlQuraOutOfRangeException}; use
     * `Instant::fromHijriCivil` directly if you need the tabular civil
     * variant.
     */
    function hdate(int $year, int $month, int $day, int $hour = 0, int $minute = 0, int $second = 0, ?string $tz = null): Instant
    {
        return Instant::fromHijri($year, $month, $day, $hour, $minute, $second, $tz);
    }
}
