<?php

declare(strict_types=1);

namespace Eram\Daynum\Tests\Conformance\Support;

/**
 * Iterates over the rows in a committed gzipped JSONL fixture, skipping the
 * leading metadata header line.
 *
 * Conformance tests use a generator so memory stays flat regardless of how
 * many rows the fixture holds.
 *
 * The large calendar fixtures can be sampled: with a stride above 1 only
 * rows whose JDN is a multiple of the stride are decoded. The mutation job
 * sets `DAYNUM_FIXTURE_STRIDE` so every mutant still meets a deterministic
 * slice of the ICU oracle without replaying all 220k rows.
 */
final class FixtureReader
{
    /**
     * The stride requested through `DAYNUM_FIXTURE_STRIDE` (default 1, i.e.
     * every row).
     */
    public static function stride(): int
    {
        $raw = getenv('DAYNUM_FIXTURE_STRIDE');
        if ($raw === false || $raw === '') {
            return 1;
        }

        $stride = filter_var($raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($stride === false) {
            throw new \RuntimeException("DAYNUM_FIXTURE_STRIDE must be a positive integer, got '{$raw}'");
        }

        return $stride;
    }

    /**
     * Whether the row for `$jdn` belongs to the sample at `$stride`.
     */
    public static function sampled(int $jdn, int $stride): bool
    {
        return $stride === 1 || $jdn % $stride === 0;
    }

    /**
     * Cheaply reads the JDN from a raw `{"jdn":N,...}` line without decoding
     * the rest of it. Returns null for lines of any other shape.
     */
    public static function jdnOf(string $line): ?int
    {
        return sscanf($line, '{"jdn":%d')[0] ?? null;
    }

    /**
     * @param int $stride Keep only rows whose JDN is a multiple of this; rows
     *                    without a JDN are always kept.
     * @return \Generator<int, array<string, mixed>>
     */
    public static function rows(string $path, int $stride = 1): \Generator
    {
        $fp = gzopen($path, 'r');
        if ($fp === false) {
            throw new \RuntimeException("Cannot open fixture {$path}");
        }

        try {
            // Skip header line.
            gzgets($fp);

            while (($line = gzgets($fp)) !== false) {
                $trimmed = trim($line);
                if ($trimmed === '') {
                    continue;
                }
                if ($stride > 1) {
                    $jdn = self::jdnOf($trimmed);
                    if ($jdn !== null && !self::sampled($jdn, $stride)) {
                        continue;
                    }
                }
                yield json_decode($trimmed, true, 5, JSON_THROW_ON_ERROR);
            }
        } finally {
            gzclose($fp);
        }
    }
}
