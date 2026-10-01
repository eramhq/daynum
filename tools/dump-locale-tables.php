<?php

declare(strict_types=1);

/**
 * Print ICU's month, weekday and AM/PM names for a locale as PHP array
 * literals, ready to paste into a `src/Locale/*Locale.php` class.
 *
 * Uses the same ICU patterns as tools/generate-format-tokens.php (`MMMM`,
 * `MMM`, `EEEE`, `EEE`), so a class built from this output matches the
 * format-token fixtures by construction. Season names, relative-time
 * phrases and week data are not printed: CLDR has no season names, and the
 * other two are checked against their own oracles.
 *
 * Requires: PHP with `ext-intl`.
 *
 * Usage:
 *   php tools/dump-locale-tables.php ps-AF [ur-PK tr-TR ...]
 */

if (!extension_loaded('intl')) {
    fwrite(STDERR, "ext-intl is required. Install php-intl.\n");
    exit(1);
}

if ($argc < 2) {
    fwrite(STDERR, "Usage: php tools/dump-locale-tables.php <icu-locale> [...]\n");
    exit(1);
}

/** Daynum locale family => ICU calendar keyword. */
const FAMILIES = [
    'gregorian' => 'gregory',
    'jalali'    => 'persian',
    'hijri'     => 'islamic-civil',
];

/**
 * Format one instant with an ICU pattern under the given calendar.
 */
function icuFormat(string $locale, string $calendar, string $pattern, float $epochMs): string
{
    $fmt = new IntlDateFormatter(
        "{$locale}-u-ca-{$calendar}-nu-latn",
        IntlDateFormatter::FULL,
        IntlDateFormatter::FULL,
        'UTC',
        $calendar === 'gregory' ? IntlDateFormatter::GREGORIAN : IntlDateFormatter::TRADITIONAL,
        $pattern,
    );
    $out = $fmt->format($epochMs / 1000);
    if ($out === false) {
        throw new RuntimeException("ICU could not format {$pattern} for {$locale}/{$calendar}");
    }
    return $out;
}

/**
 * The 15th of each month of one year of `$calendar`, as epoch milliseconds.
 *
 * @return array<int, float> month (1..12) => epoch ms
 */
function midMonths(string $locale, string $calendar): array
{
    $cal = IntlCalendar::createInstance('UTC', "{$locale}-u-ca-{$calendar}");
    $cal->setTime(gmmktime(12, 0, 0, 6, 1, 2026) * 1000.0);
    $year = $cal->get(IntlCalendar::FIELD_YEAR);

    $out = [];
    for ($m = 1; $m <= 12; $m++) {
        $cal->clear();
        $cal->set(IntlCalendar::FIELD_YEAR, $year);
        $cal->set(IntlCalendar::FIELD_MONTH, $m - 1);
        $cal->set(IntlCalendar::FIELD_DAY_OF_MONTH, 15);
        $cal->set(IntlCalendar::FIELD_HOUR_OF_DAY, 12);
        $out[$m] = $cal->getTime();
    }
    return $out;
}

/**
 * @param array<int, string> $values
 */
function phpArray(array $values, int $indent): string
{
    $pad = str_repeat(' ', $indent);
    $items = [];
    foreach ($values as $key => $value) {
        $items[] = sprintf('%d => %s', $key, var_export($value, true));
    }
    $lines = array_map(
        static fn(array $row): string => $pad . '    ' . implode(', ', $row) . ',',
        array_chunk($items, 4),
    );
    return "[\n" . implode("\n", $lines) . "\n{$pad}]";
}

foreach (array_slice($argv, 1) as $locale) {
    echo "// ── {$locale} (ICU " . INTL_ICU_VERSION . ") ──\n\n";

    foreach (['LONG_MONTHS' => 'MMMM', 'SHORT_MONTHS' => 'MMM'] as $const => $pattern) {
        echo "private const {$const} = [\n";
        foreach (FAMILIES as $family => $calendar) {
            $names = array_map(
                static fn(float $ms): string => icuFormat($locale, $calendar, $pattern, $ms),
                midMonths($locale, $calendar),
            );
            echo "    '{$family}' => " . phpArray($names, 4) . ",\n";
        }
        echo "];\n\n";
    }

    // 2024-01-07 is a Sunday; PHP day-of-week order is Sunday = 0.
    $sunday = gmmktime(12, 0, 0, 1, 7, 2024) * 1000.0;
    foreach (['LONG_WEEKDAYS' => 'EEEE', 'SHORT_WEEKDAYS' => 'EEE'] as $const => $pattern) {
        $names = [];
        for ($w = 0; $w <= 6; $w++) {
            $names[$w] = icuFormat($locale, 'gregory', $pattern, $sunday + $w * 86_400_000);
        }
        echo "private const {$const} = " . phpArray($names, 0) . ";\n\n";
    }

    $am = icuFormat($locale, 'gregory', 'a', gmmktime(3, 0, 0, 1, 7, 2024) * 1000.0);
    $pm = icuFormat($locale, 'gregory', 'a', gmmktime(15, 0, 0, 1, 7, 2024) * 1000.0);
    echo "// meridiem: AM = " . var_export($am, true) . ", PM = " . var_export($pm, true) . "\n\n";
}
