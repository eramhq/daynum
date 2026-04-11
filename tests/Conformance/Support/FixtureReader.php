<?php

declare(strict_types=1);

namespace Eram\Daynum\Tests\Conformance\Support;

/**
 * Iterates over the rows in a committed gzipped JSONL fixture, skipping the
 * leading metadata header line.
 *
 * Conformance tests use a generator so memory stays flat regardless of how
 * many rows the fixture holds.
 */
final class FixtureReader
{
    /**
     * @return \Generator<int, array<string, mixed>>
     */
    public static function rows(string $path): \Generator
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
                yield json_decode($trimmed, true, 5, JSON_THROW_ON_ERROR);
            }
        } finally {
            gzclose($fp);
        }
    }
}
