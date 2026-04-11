<?php

declare(strict_types=1);

/**
 * Produce golden formatted strings for the format-token conformance test.
 *
 * Covers a curated set of ~1000 representative dates across both English and
 * Persian locales, formatted against every PHP `date()` token Daynum supports.
 * The expected strings are generated via ICU's `IntlDateFormatter` using ICU
 * pattern equivalents of each PHP token.
 *
 * Requires: PHP with `ext-intl`.
 *
 * Usage:
 *   php tools/generate-format-tokens.php
 *
 * Writes:
 *   tests/fixtures/format-tokens-en.jsonl.gz
 *   tests/fixtures/format-tokens-fa.jsonl.gz
 *   tests/fixtures/format-tokens-ar.jsonl.gz
 *   tests/fixtures/format-tokens-en-jalali.jsonl.gz
 *   tests/fixtures/format-tokens-fa-jalali.jsonl.gz
 *   tests/fixtures/format-tokens-en-hijri.jsonl.gz
 *   tests/fixtures/format-tokens-fa-hijri.jsonl.gz
 *   tests/fixtures/format-tokens-ar-hijri.jsonl.gz
 *
 * No `format-tokens-ar-jalali.jsonl.gz` is emitted: Arabic does not ship
 * Jalali month names (ICU's transliteration is low quality), so there is
 * nothing to cross-check against. See ArabicLocale's class docblock.
 */

if (!extension_loaded('intl')) {
    fwrite(STDERR, "ext-intl is required. Install php-intl.\n");
    exit(1);
}

require __DIR__ . '/../vendor/autoload.php';

use Eram\Daynum\Calendar\Gregorian\GregorianCalendar;

const FIXTURE_DIR = __DIR__ . '/../tests/fixtures';

if (!is_dir(FIXTURE_DIR)) {
    mkdir(FIXTURE_DIR, 0755, true);
}

/**
 * Mapping from PHP date() tokens to equivalent ICU format patterns.
 *
 * Only tokens with a CLEAN one-to-one ICU pattern are listed. Numeric
 * day-of-week (`N`, `w`), leap-year flag (`L`), days-in-month (`t`), and
 * timezone tokens (`T`, `e`) have no unambiguous ICU equivalent — those are
 * covered exhaustively by the unit tests in `tests/Unit/DateTokenFormatterTest.php`
 * instead.
 */
const TOKEN_TO_ICU = [
    'Y' => 'yyyy',
    'y' => 'yy',
    'm' => 'MM',
    'n' => 'M',
    'd' => 'dd',
    'j' => 'd',
    'D' => 'EEE',
    'l' => 'EEEE',
    'F' => 'MMMM',
    'M' => 'MMM',
    'G' => 'H',
    'H' => 'HH',
    'i' => 'mm',
    's' => 'ss',
];

// Representative date sample: every ~10 weeks across 1900..2100 Gregorian.
$samples = [];
$jdnStart = GregorianCalendar::instance()->toJdn(1900, 1, 1);
$jdnEnd   = GregorianCalendar::instance()->toJdn(2100, 12, 31);
for ($jdn = $jdnStart; $jdn <= $jdnEnd; $jdn += 73) {
    $samples[] = $jdn;
}

fprintf(STDERR, "Generating format-token fixtures over %d sample dates…\n", count($samples));

// BCP 47 locale syntax (`-u-ca-X-nu-latn`) is mandatory — ICU's older
// traditional syntax (`@calendar=X@numbers=latn`) only honors the first
// `@` extension, silently dropping the rest. Always force `nu-latn` so ICU
// emits ASCII digits; Daynum handles digit transliteration itself via
// `withDigits()`, not via the locale.
foreach ([
    ['en',   'format-tokens-en.jsonl.gz',          'en-US-u-ca-gregory-nu-latn',       'gregorian'],
    ['fa',   'format-tokens-fa.jsonl.gz',          'fa-IR-u-ca-gregory-nu-latn',       'gregorian'],
    ['ar',   'format-tokens-ar.jsonl.gz',          'ar-SA-u-ca-gregory-nu-latn',       'gregorian'],
    ['en-j', 'format-tokens-en-jalali.jsonl.gz',   'en-US-u-ca-persian-nu-latn',       'persian'],
    ['fa-j', 'format-tokens-fa-jalali.jsonl.gz',   'fa-IR-u-ca-persian-nu-latn',       'persian'],
    ['en-h', 'format-tokens-en-hijri.jsonl.gz',    'en-US-u-ca-islamic-civil-nu-latn', 'islamic-civil'],
    ['fa-h', 'format-tokens-fa-hijri.jsonl.gz',    'fa-IR-u-ca-islamic-civil-nu-latn', 'islamic-civil'],
    ['ar-h', 'format-tokens-ar-hijri.jsonl.gz',    'ar-SA-u-ca-islamic-civil-nu-latn', 'islamic-civil'],
] as [$tag, $filename, $icuLocale, $calendar]) {
    $path = FIXTURE_DIR . '/' . $filename;
    $out = fopen('compress.zlib://' . $path, 'w');

    // Deliberately no wall-clock `generated` field: it would make
    // regeneration non-deterministic and turn the oracle CI's fail-on-drift
    // check into a perpetual false alarm. The `icuVersion` field is the
    // real drift signal.
    $header = [
        'generator'  => 'generate-format-tokens.php',
        'icuVersion' => defined('INTL_ICU_VERSION') ? INTL_ICU_VERSION : 'unknown',
        'locale'     => $tag,
        'calendar'   => $calendar,
    ];
    fwrite($out, json_encode(['meta' => $header]) . "\n");

    // Hoist formatter construction out of the sample loop — one
    // IntlDateFormatter per token, reused across all rows.
    $calType = $calendar === 'gregorian'
        ? IntlDateFormatter::GREGORIAN
        : IntlDateFormatter::TRADITIONAL;
    $formatters = [];
    foreach (TOKEN_TO_ICU as $phpToken => $icuPattern) {
        $formatters[$phpToken] = new IntlDateFormatter(
            $icuLocale,
            IntlDateFormatter::FULL,
            IntlDateFormatter::FULL,
            'UTC',
            $calType,
            $icuPattern,
        );
    }

    foreach ($samples as $jdn) {
        // JDN 2440588 = 1970-01-01 00:00:00 UTC
        $ts = ($jdn - 2440588) * 86400;

        $row = ['jdn' => $jdn, 'expected' => []];
        foreach ($formatters as $phpToken => $fmt) {
            $row['expected'][$phpToken] = $fmt->format($ts);
        }

        fwrite($out, json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
    }

    fclose($out);
    fprintf(STDERR, "  wrote %s (%d rows)\n", $filename, count($samples));
}
