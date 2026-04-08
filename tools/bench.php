<?php

declare(strict_types=1);

/**
 * Daynum micro-benchmark harness.
 *
 * Pure PHP, no dependencies. Each benchmark is a named closure run N times;
 * results are printed as a fixed-width table to stdout.
 *
 * Usage:
 *   php tools/bench.php
 *   php tools/bench.php --filter=jalali
 *   php tools/bench.php --iterations=100000
 *   php -d opcache.jit=0 tools/bench.php   # JIT-off sanity run
 *
 * Numbers are steady-state per-op (warmup runs before timing). The
 * `manyYears` benchmarks exercise a 256-year working set rather than
 * a single hot year, so they show the cache+lookup cost on a wider
 * footprint.
 */

require __DIR__ . '/../vendor/autoload.php';

use Daynum\Instant;

$opts = getopt('', ['filter::', 'iterations::']);
$filter = isset($opts['filter']) && $opts['filter'] !== false ? (string) $opts['filter'] : null;
$iterations = isset($opts['iterations']) && $opts['iterations'] !== false
    ? (int) $opts['iterations']
    : 50000;
$warmupIterations = 1000;

// Reusable instants — constructed outside the timing loop so the format
// benchmarks measure conversion/format paths, not constructor allocation.
$instant = Instant::fromJalali(1405, 1, 19);
$instantGreg = Instant::fromGregorian(2026, 4, 8);
$instantHijri = Instant::fromHijri(1447, 10, 21);
$instantHijriCivil = Instant::fromHijriCivil(1447, 10, 21);

/** @var array<string, callable> */
$benchmarks = [
    'jalali.toJdn.manyYears' => static function () {
        // 256 distinct Jalali years per op, all inside MIN_YEAR..MAX_YEAR.
        for ($i = 0; $i < 256; $i++) {
            Instant::fromJalali(($i % 2000) + 1000, 1, 1);
        }
    },
    'jalali.fromJdn.manyYears' => static function () {
        for ($i = 0; $i < 256; $i++) {
            $jdn = 2200000 + $i * 365;
            (new Instant($jdn))->jalali()->year();
        }
    },
    'jalali.toJdn.warm' => static function () {
        Instant::fromJalali(1405, 1, 19);
    },
    'jalali.fromJdn.warm' => static function () use ($instant) {
        $instant->jalali()->year();
    },
    'jalali.format.numeric' => static function () use ($instant) {
        $instant->jalali()->format('Y-m-d');
    },
    'jalali.format.textual' => static function () use ($instant) {
        $instant->jalali()->format('l j F Y');
    },
    'jalali.format.persianDigits' => static function () use ($instant) {
        $instant->jalali()->withDigits('persian')->format('Y-m-d');
    },
    'jalali.format.arabDigits' => static function () use ($instant) {
        $instant->jalali()->withDigits('arab')->format('Y-m-d');
    },
    'gregorian.format.numeric' => static function () use ($instantGreg) {
        $instantGreg->gregorian()->format('Y-m-d');
    },
    'hijriCivil.format.numeric' => static function () use ($instantHijriCivil) {
        $instantHijriCivil->hijriCivil()->format('Y-m-d');
    },
    'hijri.format.numeric' => static function () use ($instantHijri) {
        $instantHijri->hijri()->format('Y-m-d');
    },
];

if ($filter !== null) {
    $benchmarks = array_filter(
        $benchmarks,
        static fn(string $name): bool => preg_match('/' . $filter . '/', $name) === 1,
        ARRAY_FILTER_USE_KEY,
    );
    if ($benchmarks === []) {
        fwrite(STDERR, "No benchmarks matched filter '{$filter}'.\n");
        exit(1);
    }
}

// Warmup pass — JIT/OPcache settle before any timing data is captured.
foreach ($benchmarks as $fn) {
    for ($i = 0; $i < $warmupIterations; $i++) {
        $fn();
    }
}

printf(
    "Daynum bench — PHP %s, JIT=%s, iterations=%d\n",
    PHP_VERSION,
    function_exists('opcache_get_status') && (opcache_get_status(false)['jit']['enabled'] ?? false) ? 'on' : 'off',
    $iterations,
);
printf("%-32s  %12s  %12s  %12s\n", 'benchmark', 'total (ms)', 'us/op', 'ops/sec');
printf("%s\n", str_repeat('-', 76));

foreach ($benchmarks as $name => $fn) {
    gc_collect_cycles();
    gc_disable();
    $start = hrtime(true);
    for ($i = 0; $i < $iterations; $i++) {
        $fn();
    }
    $elapsed = hrtime(true) - $start;
    gc_enable();

    $totalMs = $elapsed / 1_000_000;
    $usPerOp = ($elapsed / 1000) / $iterations;
    $opsPerSec = $iterations / ($elapsed / 1_000_000_000);
    printf(
        "%-32s  %12.3f  %12.3f  %12s\n",
        $name,
        $totalMs,
        $usPerOp,
        number_format($opsPerSec, 0),
    );
}

printf("\npeak memory: %s\n", format_bytes(memory_get_peak_usage(true)));

function format_bytes(int $bytes): string
{
    if ($bytes >= 1 << 20) {
        return sprintf('%.2f MiB', $bytes / (1 << 20));
    }
    if ($bytes >= 1 << 10) {
        return sprintf('%.2f KiB', $bytes / (1 << 10));
    }
    return $bytes . ' B';
}
