<?php

declare(strict_types=1);

namespace Eram\Daynum\Tests\Conformance\Support;

/**
 * Reads the native Umm al-Qura year range from the committed
 * `hijri-umalqura.jsonl.gz` fixture header.
 *
 * The conformance test stays declarative by never referencing specific
 * AH years directly — the range is whatever the fixture says it is, and
 * the bundled table (see `src/Calendar/Hijri/Table.php`) is the source
 * of truth that the fixture was built against.
 */
final class UmmAlQuraRange
{
    private static int $minYear = 0;
    private static int $maxYear = 0;

    public static function minYear(): int
    {
        self::load();
        return self::$minYear;
    }

    public static function maxYear(): int
    {
        self::load();
        return self::$maxYear;
    }

    private static function load(): void
    {
        if (self::$minYear > 0) {
            return;
        }
        $path = __DIR__ . '/../../fixtures/hijri-umalqura.jsonl.gz';
        $fp = gzopen($path, 'r');
        if ($fp === false) {
            throw new \RuntimeException("Cannot open fixture {$path}");
        }
        try {
            $headerLine = gzgets($fp);
        } finally {
            gzclose($fp);
        }
        if ($headerLine === false) {
            throw new \RuntimeException("Empty UAQ fixture {$path}");
        }
        $decoded = json_decode((string) $headerLine, true, 5, JSON_THROW_ON_ERROR);
        self::$minYear = (int) ($decoded['meta']['uaqMinYear'] ?? 0);
        self::$maxYear = (int) ($decoded['meta']['uaqMaxYear'] ?? 0);
        if (self::$minYear <= 0 || self::$maxYear <= 0) {
            throw new \RuntimeException(
                'UAQ fixture header is missing uaqMinYear/uaqMaxYear metadata; '
                . 'regenerate via tools/generate-fixtures-php.php.'
            );
        }
    }
}
