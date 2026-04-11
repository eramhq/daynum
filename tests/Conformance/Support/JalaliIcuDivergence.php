<?php

declare(strict_types=1);

namespace Eram\Daynum\Tests\Conformance\Support;

use Eram\Daynum\Calendar\Jalali\JalaliCalendar;

/**
 * Lazily derives the Jalali rows that must be skipped when comparing Daynum
 * against the current PHP-generated ICU fixture.
 *
 * We keep the long-standing Birashk-vs-ICU baseline windows, but we do not
 * trust them blindly anymore. Instead we reconcile them against the current
 * oracle pair that CI regenerated:
 *
 * - `jalali.jsonl.gz` from PHP's ext-intl ICU
 * - `jalali.node.jsonl.gz` from Node's bundled ICU
 *
 * That gives us a hybrid rule:
 * - baseline rows are skipped only if the current PHP fixture still disagrees
 *   with Daynum there;
 * - non-baseline rows are skipped only if PHP and Node disagree structurally
 *   and Daynum matches the Node side.
 *
 * Any other mismatch is preserved as a hard failure.
 */
final class JalaliIcuDivergence
{
    private const PHP_FIXTURE = __DIR__ . '/../../fixtures/jalali.jsonl.gz';
    private const NODE_FIXTURE = __DIR__ . '/../../fixtures/jalali.node.jsonl.gz';

    /**
     * Inclusive [lo, hi] JDN ranges for the long-standing Birashk-vs-ICU
     * divergence windows. These are the rows that the precommitted fixtures
     * already depend on today.
     *
     * @var list<array{int,int}>
     */
    private const BASELINE_RANGES = [
        [2341973, 2342051], //  79 days, straddling Nowruz 1078 AP / 1700 CE
        [2377845, 2378210], // 366 days, Nowruz 1177 AP / 1798 CE
        [2496914, 2497279], // 366 days, Nowruz 1503 AP / 2124 CE
        [2533073, 2533438], // 366 days, Nowruz 1602 AP / 2223 CE
    ];

    /** @var array<int, true>|null */
    private static ?array $skipMap = null;

    /** @var list<string>|null */
    private static ?array $unexpectedRows = null;

    public static function contains(int $jdn): bool
    {
        self::bootstrap();

        return isset(self::$skipMap[$jdn]);
    }

    public static function count(): int
    {
        self::bootstrap();

        return count(self::$skipMap);
    }

    /**
     * @return list<string>
     */
    public static function unexpectedRows(): array
    {
        self::bootstrap();

        return self::$unexpectedRows;
    }

    private static function bootstrap(): void
    {
        if (self::$skipMap !== null && self::$unexpectedRows !== null) {
            return;
        }

        self::$skipMap = [];
        self::$unexpectedRows = [];

        if (!is_file(self::PHP_FIXTURE) || !is_file(self::NODE_FIXTURE)) {
            self::$unexpectedRows[] = 'Missing Jalali ICU fixtures; regenerate tests/fixtures/jalali*.jsonl.gz.';
            return;
        }

        $php = gzopen(self::PHP_FIXTURE, 'r');
        $node = gzopen(self::NODE_FIXTURE, 'r');
        if ($php === false || $node === false) {
            self::$unexpectedRows[] = 'Unable to open Jalali ICU fixtures.';
            return;
        }

        gzgets($php);
        gzgets($node);

        $calendar = JalaliCalendar::instance();
        $line = 1;

        while (true) {
            $phpLine = gzgets($php);
            $nodeLine = gzgets($node);
            $line++;

            if ($phpLine === false && $nodeLine === false) {
                break;
            }

            if ($phpLine === false || $nodeLine === false) {
                self::$unexpectedRows[] = sprintf(
                    'jalali fixture length mismatch at line %d between PHP and Node oracles',
                    $line
                );
                break;
            }

            $phpRow = json_decode(trim((string) $phpLine), true);
            $nodeRow = json_decode(trim((string) $nodeLine), true);
            if (!is_array($phpRow) || !is_array($nodeRow)) {
                self::$unexpectedRows[] = sprintf(
                    'invalid Jalali fixture JSON at line %d',
                    $line
                );
                break;
            }

            $jdn = $phpRow['jdn'];
            $actual = $calendar->fromJdn($jdn);
            if ($actual === $phpRow['j']) {
                continue;
            }

            if (self::isBaselineJdn($jdn)) {
                self::$skipMap[$jdn] = true;
                continue;
            }

            if (self::isNodeBackedIcuShift($phpRow, $nodeRow, $actual, $line)) {
                self::$skipMap[$jdn] = true;
                continue;
            }

            self::$unexpectedRows[] = sprintf(
                'jdn=%d: Jalali fixture disagrees with Daynum outside the baseline allow-list (php=%s node=%s actual=%s)',
                $jdn,
                self::encode($phpRow['j']),
                self::encode($nodeRow['j']),
                self::encode($actual),
            );
        }

        gzclose($php);
        gzclose($node);
    }

    /**
     * @param mixed $value
     */
    private static function encode(mixed $value): string
    {
        $json = json_encode($value);

        return $json === false ? '<json-encode-failed>' : $json;
    }

    private static function isBaselineJdn(int $jdn): bool
    {
        foreach (self::BASELINE_RANGES as [$lo, $hi]) {
            if ($jdn >= $lo && $jdn <= $hi) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $phpRow
     * @param array<string, mixed> $nodeRow
     * @param array<int, int> $actual
     */
    private static function isNodeBackedIcuShift(array $phpRow, array $nodeRow, array $actual, int $line): bool
    {
        $sameStructure = ($phpRow['jdn'] ?? null) === ($nodeRow['jdn'] ?? null)
            && ($phpRow['g'] ?? null) === ($nodeRow['g'] ?? null)
            && ($phpRow['dow'] ?? null) === ($nodeRow['dow'] ?? null);

        if (!$sameStructure) {
            self::$unexpectedRows[] = sprintf(
                'jalali fixture row %d is not a structural ICU-only divergence: php=%s node=%s',
                $line,
                self::encode($phpRow),
                self::encode($nodeRow),
            );

            return false;
        }

        if (($phpRow['j'] ?? null) === ($nodeRow['j'] ?? null)) {
            return false;
        }

        if ($actual === $nodeRow['j']) {
            return true;
        }

        return false;
    }
}
