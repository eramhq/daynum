<?php

declare(strict_types=1);

/**
 * Compare freshly regenerated oracle fixtures against the committed ones,
 * looking at data only.
 *
 * Every fixture's first line is a metadata header recording the ICU version
 * it was built with, and Table.php records the same in ICU_VERSION and its
 * docblock. Those legitimately differ whenever the CI runner's ICU differs
 * from the maintainer's, so comparing raw bytes fails on every run. This
 * script ignores them and fails only when the data rows (or the UAQ table
 * data) changed, printing the first differing rows so the cause is visible
 * in the CI log.
 *
 * Usage (after regenerating, in a git checkout):
 *   php tools/check-fixture-drift.php
 *
 * Exit status: 0 = no data drift (header-only differences are reported as
 * warnings), 1 = data drift.
 */

const MAX_REPORTED_ROWS = 10;

/**
 * @return list<string>
 */
function gitLines(string $args): array
{
    exec('git ' . $args, $out, $status);
    if ($status !== 0) {
        fwrite(STDERR, "git {$args} failed\n");
        exit(2);
    }
    return $out;
}

/**
 * @return list<string> data lines (header dropped)
 */
function fixtureRows(string $gzipped): array
{
    $text = gzdecode($gzipped);
    if ($text === false) {
        throw new RuntimeException('not gzip data');
    }
    $lines = explode("\n", rtrim($text, "\n"));
    array_shift($lines);
    return $lines;
}

function committed(string $path): string
{
    $out = shell_exec('git show ' . escapeshellarg('HEAD:' . $path));
    return is_string($out) ? $out : '';
}

$dataDrift = false;

foreach (gitLines('diff --name-only -- tests/fixtures/') as $path) {
    if (!str_ends_with($path, '.jsonl.gz')) {
        continue;
    }
    $before = fixtureRows(committed($path));
    $after = fixtureRows((string) file_get_contents($path));

    if ($before === $after) {
        echo "::warning::{$path}: header changed (ICU version), data identical\n";
        continue;
    }

    $dataDrift = true;
    echo "::error::{$path}: data changed (" . count($before) . ' → ' . count($after) . " rows)\n";
    $reported = 0;
    $max = max(count($before), count($after));
    for ($i = 0; $i < $max && $reported < MAX_REPORTED_ROWS; $i++) {
        $old = $before[$i] ?? '<missing>';
        $new = $after[$i] ?? '<missing>';
        if ($old !== $new) {
            echo "  - {$old}\n  + {$new}\n";
            $reported++;
        }
    }
}

// Table.php: everything except the version / timestamp metadata must match.
$table = 'src/Calendar/Hijri/Table.php';
$metadata = '/GENERATED_AT|ICU_VERSION|bundled `islamic-umalqura` data/';
$strip = static fn (string $src): array => array_values(array_filter(
    explode("\n", $src),
    static fn (string $line): bool => preg_match($metadata, $line) !== 1,
));
$tableBefore = $strip(committed($table));
$tableAfter = $strip((string) file_get_contents($table));
if ($tableBefore !== $tableAfter) {
    $dataDrift = true;
    echo "::error::{$table}: Umm al-Qura data changed\n";
    passthru("git diff --ignore-matching-lines='GENERATED_AT|ICU_VERSION|bundled' -- " . escapeshellarg($table));
} elseif (gitLines('diff --name-only -- ' . escapeshellarg($table)) !== []) {
    echo "::warning::{$table}: only ICU version metadata changed\n";
}

if ($dataDrift) {
    echo "::error::Oracle data drifted. Regenerate locally (see tests/fixtures/README.md) and review before committing.\n";
    exit(1);
}

echo "No fixture or Table.php data drift.\n";
