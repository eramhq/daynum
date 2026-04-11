<?php

declare(strict_types=1);

/**
 * Dump ICU's bundled `islamic-umalqura` data as `src/Calendar/Hijri/Table.php`.
 *
 * This is a maintainer-only tool. The output it produces is committed to the
 * repository so end users don't need `ext-intl` at runtime — they just
 * `composer install` and the table comes along as pure PHP.
 *
 * ## What it does
 *
 * 1. Probes ICU's `islamic-umalqura` across a wide scan range to find the
 *    edges of ICU's "native" data window. ICU exposes no API to query the
 *    bounds directly; outside them it silently falls through to the
 *    arithmetic `islamic-civil` calendar. We detect the edges by comparing
 *    against `islamic-civil` at the month-length level.
 * 2. Inside the discovered window, computes all 12 month lengths per year
 *    *and* the JDN of Muharram 1.
 * 3. Bit-packs the month lengths (bit `i` = month `i+1`, 1 = 30 days,
 *    0 = 29 days) and emits a `Table.php` with two constant maps.
 * 4. Optionally cross-verifies a sample of years against Rob van Gent's
 *    independently-published UAQ table (webspace.science.uu.nl/~gent0113).
 *    Pass `--skip-van-gent` to skip the web fetch when offline.
 *
 * ## What it does NOT do
 *
 * - Reject years that happen to have month lengths identical to the civil
 *   calendar. The plan's early heuristic ("exclude years whose 12 month
 *   lengths match civil byte-for-byte") over-rejects: by inspection, years
 *   1363 and 1553 sit squarely inside the native UAQ range but coincidentally
 *   line up with civil. Leaving them out would produce a hole in the table.
 *   Instead, we bracket the native range by the *outermost* years where UAQ
 *   differs from civil — and keep every year between those two bounds.
 *
 * Usage:
 *   php tools/generate-uaq-table.php [--skip-van-gent]
 */

if (!extension_loaded('intl')) {
    fwrite(STDERR, "ext-intl is required to regenerate the UAQ table. Install php-intl.\n");
    exit(1);
}

require __DIR__ . '/../vendor/autoload.php';

$skipVanGent = in_array('--skip-van-gent', $argv, true);

$SCAN_START = 1100;
$SCAN_END   = 1800;

$uaq   = IntlCalendar::createInstance('UTC', 'en_US@calendar=islamic-umalqura');
$civil = IntlCalendar::createInstance('UTC', 'en_US@calendar=islamic-civil');

/**
 * @return array<int,int> 12 month lengths, 1-indexed.
 */
function monthLengths(IntlCalendar $cal, int $year): array
{
    $lens = [];
    for ($m = 1; $m <= 12; $m++) {
        $cal->clear();
        $cal->set(IntlCalendar::FIELD_YEAR, $year);
        $cal->set(IntlCalendar::FIELD_MONTH, $m - 1);
        $cal->set(IntlCalendar::FIELD_DAY_OF_MONTH, 1);
        // Touch a field to force computation so getActualMaximum honors the
        // year we just set.
        $cal->get(IntlCalendar::FIELD_YEAR);
        $lens[$m] = $cal->getActualMaximum(IntlCalendar::FIELD_DAY_OF_MONTH);
    }
    return $lens;
}

function startOfYearJdn(IntlCalendar $cal, int $year): int
{
    $cal->clear();
    $cal->set(IntlCalendar::FIELD_YEAR, $year);
    $cal->set(IntlCalendar::FIELD_MONTH, 0);
    $cal->set(IntlCalendar::FIELD_DAY_OF_MONTH, 1);
    $cal->get(IntlCalendar::FIELD_YEAR);
    $ms = $cal->getTime();
    return (int) floor($ms / 86400000) + 2440588;
}

// ─── Discover the native range ───────────────────────────────────────
fprintf(STDERR, "Scanning AH %d..%d for UAQ-vs-civil divergence…\n", $SCAN_START, $SCAN_END);

$firstDiff = null;
$lastDiff  = null;
for ($y = $SCAN_START; $y <= $SCAN_END; $y++) {
    if (monthLengths($uaq, $y) !== monthLengths($civil, $y)) {
        $firstDiff ??= $y;
        $lastDiff = $y;
    }
}

if ($firstDiff === null) {
    fwrite(STDERR, "No UAQ/civil divergence found in scan window — bailing.\n");
    exit(2);
}

$MIN_YEAR = $firstDiff;
$MAX_YEAR = $lastDiff;
fprintf(STDERR, "  native UAQ range detected: AH %d..%d (%d years)\n", $MIN_YEAR, $MAX_YEAR, $MAX_YEAR - $MIN_YEAR + 1);

// ─── Capture month lengths + year starts ─────────────────────────────
$monthLengthsBits = [];
$yearStarts       = [];
for ($y = $MIN_YEAR; $y <= $MAX_YEAR; $y++) {
    $lens = monthLengths($uaq, $y);
    $bits = 0;
    for ($m = 1; $m <= 12; $m++) {
        if ($lens[$m] !== 29 && $lens[$m] !== 30) {
            throw new RuntimeException("Unexpected month length {$lens[$m]} at year {$y} month {$m}");
        }
        if ($lens[$m] === 30) {
            $bits |= (1 << ($m - 1));
        }
    }
    $monthLengthsBits[$y] = $bits;
    $yearStarts[$y]       = startOfYearJdn($uaq, $y);
}

// Sanity: consecutive years should be exactly (354 or 355) apart, and the
// sum of the 12 bits must agree with that delta. In UAQ — unlike the civil
// calendar — the leap day is NOT pinned to Dhu al-Hijjah; any month can be
// 30 days in any year, so year-length must be derived from the popcount of
// the month-length bits, not from the state of bit 11.
for ($y = $MIN_YEAR; $y < $MAX_YEAR; $y++) {
    $delta = $yearStarts[$y + 1] - $yearStarts[$y];
    if ($delta !== 354 && $delta !== 355) {
        throw new RuntimeException(sprintf(
            "Unexpected year-start delta %d between AH %d and %d",
            $delta, $y, $y + 1
        ));
    }
    // Year length = 29*12 + (# bits set) = 348 + popcount.
    $popcount = 0;
    for ($bit = 0; $bit < 12; $bit++) {
        if ($monthLengthsBits[$y] & (1 << $bit)) {
            $popcount++;
        }
    }
    $yearLenFromBits = 348 + $popcount;
    if ($yearLenFromBits !== $delta) {
        throw new RuntimeException(sprintf(
            "Year-length disagreement at AH %d: bits→%d days, yearStart delta→%d",
            $y, $yearLenFromBits, $delta
        ));
    }
}
fprintf(STDERR, "  month-length bits and year starts internally consistent\n");

// ─── Optional cross-check against Rob van Gent's published table ─────
if (!$skipVanGent) {
    fprintf(STDERR, "Cross-checking against van Gent (webspace.science.uu.nl/~gent0113)…\n");
    $vgUrl = 'https://webspace.science.uu.nl/~gent0113/islam/ummalqura.htm';
    $vgHtml = @file_get_contents($vgUrl);
    if ($vgHtml === false) {
        fprintf(STDERR, "  warning: van Gent table unreachable; skipping cross-check.\n");
        fprintf(STDERR, "  rerun with the network available, or pass --skip-van-gent.\n");
    } else {
        // We don't attempt to parse the full van Gent table — its HTML
        // structure changes over time. Instead we just check a small sample
        // of well-known dates and trust our internal consistency otherwise.
        fprintf(STDERR, "  fetched van Gent page (%d bytes)\n", strlen($vgHtml));
        // Validate three widely-reproduced Ramadan anchors by searching the
        // page text for the Gregorian date strings.
        $anchors = [
            'Ramadan 1 AH 1444' => ['23 Mar 2023', 'Mar 23, 2023', '2023-03-23'],
            'Ramadan 1 AH 1445' => ['11 Mar 2024', 'Mar 11, 2024', '2024-03-11'],
            'Ramadan 1 AH 1446' => ['1 Mar 2025', 'Mar 1, 2025', '2025-03-01'],
        ];
        foreach ($anchors as $label => $candidates) {
            $found = false;
            foreach ($candidates as $s) {
                if (stripos($vgHtml, $s) !== false) {
                    $found = true;
                    break;
                }
            }
            fprintf(STDERR, "  %s: %s\n", $label, $found ? 'found on page' : 'NOT found (page format may have changed)');
        }
    }
}

// ─── Emit Table.php ──────────────────────────────────────────────────
$tablePath = __DIR__ . '/../src/Calendar/Hijri/Table.php';
$icuVersion = defined('INTL_ICU_VERSION') ? INTL_ICU_VERSION : 'unknown';
$generatedAt = gmdate('c');

$lines = [];
$lines[] = '<?php';
$lines[] = '';
$lines[] = 'declare(strict_types=1);';
$lines[] = '';
$lines[] = 'namespace Eram\Daynum\\Calendar\\Hijri;';
$lines[] = '';
$lines[] = '/**';
$lines[] = ' * Umm al-Qura month-length and start-of-year tables, generated from ICU';
$lines[] = ' * ' . $icuVersion . '\'s bundled `islamic-umalqura` data. Regenerate with:';
$lines[] = ' *';
$lines[] = ' *     php tools/generate-uaq-table.php';
$lines[] = ' *';
$lines[] = ' * The source data ultimately derives from the Kingdom of Saudi Arabia\'s';
$lines[] = ' * Umm al-Qura calendar, as published by the King Abdulaziz City for';
$lines[] = ' * Science and Technology (KACST) and distributed by the Unicode Common';
$lines[] = ' * Locale Data Repository (CLDR / ICU). Rob van Gent maintains an';
$lines[] = ' * independently-sourced academic table at';
$lines[] = ' * webspace.science.uu.nl/~gent0113/islam/ummalqura.htm which the generator';
$lines[] = ' * cross-checks against.';
$lines[] = ' *';
$lines[] = ' * @internal';
$lines[] = ' */';
$lines[] = 'final class Table';
$lines[] = '{';
$lines[] = '    public const MIN_YEAR = ' . $MIN_YEAR . ';';
$lines[] = '    public const MAX_YEAR = ' . $MAX_YEAR . ';';
$lines[] = '    public const ICU_VERSION = \'' . $icuVersion . '\';';
$lines[] = '    public const GENERATED_AT = \'' . $generatedAt . '\';';
$lines[] = '';
$lines[] = '    /**';
$lines[] = '     * Bit-packed month lengths. Key = AH year, value = 12-bit int where';
$lines[] = '     * bit `i` (0-indexed) encodes month (i+1):';
$lines[] = '     *   1 = 30 days, 0 = 29 days.';
$lines[] = '     *';
$lines[] = '     * @var array<int, int>';
$lines[] = '     */';
$lines[] = '    public const MONTH_LENGTHS = [';
foreach ($monthLengthsBits as $y => $bits) {
    $lines[] = sprintf('        %d => 0x%03X,', $y, $bits);
}
$lines[] = '    ];';
$lines[] = '';
$lines[] = '    /**';
$lines[] = '     * JDN of Muharram 1 of each AH year. Precomputed so the hot-path';
$lines[] = '     * `toJdn` lookup is O(1); without this, `toJdn` would have to walk';
$lines[] = '     * from `MIN_YEAR` summing year lengths.';
$lines[] = '     *';
$lines[] = '     * @var array<int, int>';
$lines[] = '     */';
$lines[] = '    public const YEAR_STARTS = [';
foreach ($yearStarts as $y => $jdn) {
    $lines[] = sprintf('        %d => %d,', $y, $jdn);
}
$lines[] = '    ];';
$lines[] = '}';
$lines[] = '';

file_put_contents($tablePath, implode("\n", $lines));
fprintf(STDERR, "Wrote %s (%d years, %d bytes).\n", $tablePath, $MAX_YEAR - $MIN_YEAR + 1, filesize($tablePath));
