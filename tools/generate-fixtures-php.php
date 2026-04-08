<?php

declare(strict_types=1);

/**
 * Generate ICU-based oracle fixtures for Gregorian and Jalali conformance.
 *
 * Iterates Gregorian dates from 1700-01-01 to 2300-12-31 and emits, for every
 * day, the authoritative Gregorian and Jalali (`persian`) components as
 * reported by PHP's `ext-intl` (= bundled ICU). The output is gzipped JSONL so
 * developers can run the conformance suite without needing `ext-intl` locally.
 *
 * Requires: PHP with `ext-intl` enabled.
 *
 * Usage:
 *   php tools/generate-fixtures-php.php
 *
 * Writes:
 *   tests/fixtures/gregorian.jsonl.gz
 *   tests/fixtures/jalali.jsonl.gz
 *
 * IMPORTANT: The `IntlCalendar` API is 0-indexed for months; we translate to
 * 1-indexed months before writing, to match Daynum's convention.
 */

if (!extension_loaded('intl')) {
    fwrite(STDERR, "ext-intl is required to regenerate oracle fixtures. Install php-intl.\n");
    exit(1);
}

require __DIR__ . '/../vendor/autoload.php';

use Daynum\Calendar\Gregorian\GregorianCalendar;

const FIXTURE_DIR = __DIR__ . '/../tests/fixtures';
const START_YEAR = 1700;
const END_YEAR   = 2300;

if (!is_dir(FIXTURE_DIR)) {
    mkdir(FIXTURE_DIR, 0755, true);
}

$icuVersion = defined('INTL_ICU_VERSION') ? INTL_ICU_VERSION : 'unknown';
$phpVersion = PHP_VERSION;
$timestamp  = gmdate('c');

$header = [
    'generator'  => 'generate-fixtures-php.php',
    'phpVersion' => $phpVersion,
    'icuVersion' => $icuVersion,
    'generated'  => $timestamp,
    'range'      => sprintf('%d-01-01..%d-12-31', START_YEAR, END_YEAR),
];

$gregFile = fopen('compress.zlib://' . FIXTURE_DIR . '/gregorian.jsonl.gz', 'w');
$jalFile  = fopen('compress.zlib://' . FIXTURE_DIR . '/jalali.jsonl.gz', 'w');

fwrite($gregFile, json_encode(['meta' => $header + ['calendar' => 'gregorian']]) . "\n");
fwrite($jalFile,  json_encode(['meta' => $header + ['calendar' => 'persian']]) . "\n");

// Oracles
$gregCal = IntlCalendar::createInstance('UTC', 'en_US@calendar=gregorian');
$persCal = IntlCalendar::createInstance('UTC', 'fa_IR@calendar=persian');

$gregCal->clear();
$count = 0;
$startJdn = GregorianCalendar::instance()->toJdn(START_YEAR, 1, 1);
$endJdn   = GregorianCalendar::instance()->toJdn(END_YEAR, 12, 31);

for ($jdn = $startJdn; $jdn <= $endJdn; $jdn++) {
    // Seed both calendars from a UTC timestamp derived from the JDN. The
    // reference epoch is JDN 2440588 = 1970-01-01 00:00:00 UTC.
    $unix = ($jdn - 2440588) * 86400;

    $gregCal->clear();
    $gregCal->setTime($unix * 1000.0);
    $persCal->clear();
    $persCal->setTime($unix * 1000.0);

    $gy = $gregCal->get(IntlCalendar::FIELD_YEAR);
    $gm = $gregCal->get(IntlCalendar::FIELD_MONTH) + 1; // 0-indexed → 1-indexed
    $gd = $gregCal->get(IntlCalendar::FIELD_DAY_OF_MONTH);

    $jy = $persCal->get(IntlCalendar::FIELD_YEAR);
    $jm = $persCal->get(IntlCalendar::FIELD_MONTH) + 1; // 0-indexed → 1-indexed
    $jd = $persCal->get(IntlCalendar::FIELD_DAY_OF_MONTH);

    // ICU FIELD_DAY_OF_WEEK is 1=Sun..7=Sat. Convert to PHP 0=Sun..6=Sat.
    $dow = $gregCal->get(IntlCalendar::FIELD_DAY_OF_WEEK) - 1;

    fwrite($gregFile, json_encode([
        'jdn' => $jdn,
        'g'   => [$gy, $gm, $gd],
        'dow' => $dow,
    ], JSON_UNESCAPED_SLASHES) . "\n");

    fwrite($jalFile, json_encode([
        'jdn' => $jdn,
        'g'   => [$gy, $gm, $gd],
        'j'   => [$jy, $jm, $jd],
        'dow' => $dow,
    ], JSON_UNESCAPED_SLASHES) . "\n");

    $count++;
    if ($count % 50000 === 0) {
        fprintf(STDERR, "  %d rows written (%s)…\n", $count, sprintf('%d-%02d-%02d', $gy, $gm, $gd));
    }
}

fclose($gregFile);
fclose($jalFile);

fprintf(STDERR, "Done. %d rows per calendar. ICU %s.\n", $count, $icuVersion);
