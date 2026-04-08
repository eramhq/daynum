<?php

declare(strict_types=1);

/**
 * Generate ICU-based oracle fixtures for conformance testing.
 *
 * Iterates Gregorian dates from 1700-01-01 to 2300-12-31 and emits, for every
 * day, the authoritative Gregorian, Jalali (`persian`), Hijri civil
 * (`islamic-civil`), and Hijri Umm al-Qura (`islamic-umalqura`, restricted to
 * ICU's native UAQ range) components as reported by PHP's `ext-intl`
 * (= bundled ICU). The output is gzipped JSONL so developers can run the
 * conformance suite without needing `ext-intl` locally.
 *
 * Requires: PHP with `ext-intl` enabled.
 *
 * Usage:
 *   php tools/generate-fixtures-php.php
 *
 * Writes:
 *   tests/fixtures/gregorian.jsonl.gz
 *   tests/fixtures/jalali.jsonl.gz
 *   tests/fixtures/hijri-civil.jsonl.gz
 *   tests/fixtures/hijri-umalqura.jsonl.gz
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

// Deliberately no wall-clock `generated` field: it would make regeneration
// non-deterministic and turn the oracle CI's fail-on-drift check into a
// perpetual false alarm. The `icuVersion` field is the real drift signal.
$header = [
    'generator'  => 'generate-fixtures-php.php',
    'phpVersion' => $phpVersion,
    'icuVersion' => $icuVersion,
    'range'      => sprintf('%d-01-01..%d-12-31', START_YEAR, END_YEAR),
];

$gregFile = fopen('compress.zlib://' . FIXTURE_DIR . '/gregorian.jsonl.gz', 'w');
$jalFile  = fopen('compress.zlib://' . FIXTURE_DIR . '/jalali.jsonl.gz', 'w');
$hcFile   = fopen('compress.zlib://' . FIXTURE_DIR . '/hijri-civil.jsonl.gz', 'w');
$uaqFile  = fopen('compress.zlib://' . FIXTURE_DIR . '/hijri-umalqura.jsonl.gz', 'w');

// UAQ range is derived from the bundled Daynum table at generation time.
// If the table hasn't been generated yet (first run), the UAQ fixture is
// simply produced over an empty range — running the tool a second time
// after `tools/generate-uaq-table.php` completes will populate it.
$uaqRange = null;
if (class_exists(\Daynum\Calendar\Hijri\Table::class)) {
    $uaqRange = [
        \Daynum\Calendar\Hijri\Table::MIN_YEAR,
        \Daynum\Calendar\Hijri\Table::MAX_YEAR,
    ];
}

fwrite($gregFile, json_encode(['meta' => $header + ['calendar' => 'gregorian']]) . "\n");
fwrite($jalFile,  json_encode(['meta' => $header + ['calendar' => 'persian']]) . "\n");
fwrite($hcFile,   json_encode(['meta' => $header + ['calendar' => 'islamic-civil']]) . "\n");
fwrite($uaqFile,  json_encode(['meta' => $header + [
    'calendar'    => 'islamic-umalqura',
    'uaqMinYear'  => $uaqRange[0] ?? null,
    'uaqMaxYear'  => $uaqRange[1] ?? null,
]]) . "\n");

// Oracles
$gregCal = IntlCalendar::createInstance('UTC', 'en_US@calendar=gregorian');
$persCal = IntlCalendar::createInstance('UTC', 'fa_IR@calendar=persian');
$hcCal   = IntlCalendar::createInstance('UTC', 'en_US@calendar=islamic-civil');
$uaqCal  = IntlCalendar::createInstance('UTC', 'en_US@calendar=islamic-umalqura');

$gregCal->clear();
$count = 0;
$uaqCount = 0;
$startJdn = GregorianCalendar::instance()->toJdn(START_YEAR, 1, 1);
$endJdn   = GregorianCalendar::instance()->toJdn(END_YEAR, 12, 31);

for ($jdn = $startJdn; $jdn <= $endJdn; $jdn++) {
    // Seed both calendars from a UTC timestamp derived from the JDN. The
    // reference epoch is JDN 2440588 = 1970-01-01 00:00:00 UTC.
    $unix = ($jdn - 2440588) * 86400;
    $ms = $unix * 1000.0;

    $gregCal->clear();
    $gregCal->setTime($ms);
    $persCal->clear();
    $persCal->setTime($ms);
    $hcCal->clear();
    $hcCal->setTime($ms);
    $uaqCal->clear();
    $uaqCal->setTime($ms);

    $gy = $gregCal->get(IntlCalendar::FIELD_YEAR);
    $gm = $gregCal->get(IntlCalendar::FIELD_MONTH) + 1; // 0-indexed → 1-indexed
    $gd = $gregCal->get(IntlCalendar::FIELD_DAY_OF_MONTH);

    $jy = $persCal->get(IntlCalendar::FIELD_YEAR);
    $jm = $persCal->get(IntlCalendar::FIELD_MONTH) + 1; // 0-indexed → 1-indexed
    $jd = $persCal->get(IntlCalendar::FIELD_DAY_OF_MONTH);

    $hcy = $hcCal->get(IntlCalendar::FIELD_YEAR);
    $hcm = $hcCal->get(IntlCalendar::FIELD_MONTH) + 1;
    $hcd = $hcCal->get(IntlCalendar::FIELD_DAY_OF_MONTH);

    $uaqY = $uaqCal->get(IntlCalendar::FIELD_YEAR);
    $uaqM = $uaqCal->get(IntlCalendar::FIELD_MONTH) + 1;
    $uaqD = $uaqCal->get(IntlCalendar::FIELD_DAY_OF_MONTH);

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

    fwrite($hcFile, json_encode([
        'jdn' => $jdn,
        'g'   => [$gy, $gm, $gd],
        'h'   => [$hcy, $hcm, $hcd],
        'dow' => $dow,
    ], JSON_UNESCAPED_SLASHES) . "\n");

    // Emit the UAQ row only inside the native UAQ year range. Outside
    // that window ICU silently falls back to `islamic-civil`, which is
    // the exact credibility hazard Daynum exists to avoid — the row
    // would be meaningless as a UAQ oracle.
    if ($uaqRange !== null && $uaqY >= $uaqRange[0] && $uaqY <= $uaqRange[1]) {
        fwrite($uaqFile, json_encode([
            'jdn' => $jdn,
            'g'   => [$gy, $gm, $gd],
            'h'   => [$uaqY, $uaqM, $uaqD],
            'dow' => $dow,
        ], JSON_UNESCAPED_SLASHES) . "\n");
        $uaqCount++;
    }

    $count++;
    if ($count % 50000 === 0) {
        fprintf(STDERR, "  %d rows written (%s)…\n", $count, sprintf('%d-%02d-%02d', $gy, $gm, $gd));
    }
}

fclose($gregFile);
fclose($jalFile);
fclose($hcFile);
fclose($uaqFile);

fprintf(
    STDERR,
    "Done. %d rows per base calendar, %d UAQ rows in range. ICU %s.\n",
    $count,
    $uaqCount,
    $icuVersion,
);
