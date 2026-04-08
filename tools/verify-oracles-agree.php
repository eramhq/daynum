<?php

declare(strict_types=1);

/**
 * Diff the PHP-generated and Node-generated oracle fixtures row-by-row.
 *
 * Metadata headers differ by design (they record the generator, ICU version,
 * and timestamp) so the first line of each file is skipped. Every subsequent
 * line must match byte-for-byte; the first disagreement prints a diff and
 * exits non-zero.
 *
 * Usage:
 *   php tools/verify-oracles-agree.php
 */

const FIXTURE_DIR = __DIR__ . '/../tests/fixtures';

$pairs = [
    ['gregorian.jsonl.gz', 'gregorian.node.jsonl.gz'],
    ['jalali.jsonl.gz',    'jalali.node.jsonl.gz'],
];

$overallOk = true;

foreach ($pairs as [$phpFile, $nodeFile]) {
    $phpPath  = FIXTURE_DIR . '/' . $phpFile;
    $nodePath = FIXTURE_DIR . '/' . $nodeFile;

    if (!file_exists($phpPath)) {
        fwrite(STDERR, "Missing fixture: {$phpPath} — run generate-fixtures-php.php first.\n");
        $overallOk = false;
        continue;
    }
    if (!file_exists($nodePath)) {
        fwrite(STDERR, "Missing fixture: {$nodePath} — run generate-fixtures-node.mjs first.\n");
        $overallOk = false;
        continue;
    }

    $php = gzopen($phpPath, 'r');
    $node = gzopen($nodePath, 'r');

    // Skip and record the header lines — they're expected to differ.
    $phpHeader  = trim((string) gzgets($php));
    $nodeHeader = trim((string) gzgets($node));

    fprintf(STDOUT, "Comparing %s ↔ %s\n", $phpFile, $nodeFile);
    fprintf(STDOUT, "  php header:  %s\n", $phpHeader);
    fprintf(STDOUT, "  node header: %s\n", $nodeHeader);

    $line = 1; // the headers were line 1
    $diffs = 0;
    while (true) {
        $a = gzgets($php);
        $b = gzgets($node);
        $line++;

        if ($a === false && $b === false) {
            break;
        }
        if ($a === false || $b === false) {
            fprintf(STDERR, "  LENGTH MISMATCH at line %d\n", $line);
            $overallOk = false;
            break;
        }

        if (trim((string) $a) !== trim((string) $b)) {
            if ($diffs < 5) {
                fprintf(STDERR, "  DIFF line %d\n    php:  %s    node: %s", $line, $a, $b);
            }
            $diffs++;
            $overallOk = false;
        }
    }

    gzclose($php);
    gzclose($node);

    if ($diffs === 0) {
        fprintf(STDOUT, "  ✓ fixtures agree on every row\n");
    } else {
        fprintf(STDERR, "  ✗ %d disagreeing rows\n", $diffs);
    }
}

exit($overallOk ? 0 : 1);
