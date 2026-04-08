<?php

declare(strict_types=1);

/**
 * Opt-in global helper functions mirroring the morilog/jalali API style.
 *
 * This file is NOT autoloaded by default. To enable these helpers, add the
 * following to your own project's `composer.json`:
 *
 *     "autoload": {
 *         "files": ["vendor/daynum/daynum/src/helpers.php"]
 *     }
 *
 * Each helper is wrapped in `function_exists` so Daynum will never silently
 * override a name the host application has already defined.
 */

use Daynum\Instant;

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

// `hdate()` will land alongside the Hijri Umm al-Qura calendar in a later
// milestone. Until then this file intentionally does not define it, so users
// who opt into the helpers get a clean "undefined function" at the real call
// site rather than a runtime exception from a stub they can't catch by type.
