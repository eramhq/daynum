<?php

declare(strict_types=1);

namespace Eram\Daynum\Tests\Property\Support;

/**
 * Scales property-test iteration counts by `DAYNUM_PROPERTY_SCALE` (default
 * 1.0). The mutation job lowers it so each mutant runs a short, still
 * seeded, slice of every property.
 */
final class Budget
{
    /** Never scale a property below this many iterations. */
    private const MIN_ITERATIONS = 50;

    public static function iterations(int $n): int
    {
        $raw = getenv('DAYNUM_PROPERTY_SCALE');
        if ($raw === false || $raw === '') {
            return $n;
        }

        $scale = filter_var($raw, FILTER_VALIDATE_FLOAT);
        if ($scale === false || $scale <= 0.0) {
            throw new \RuntimeException("DAYNUM_PROPERTY_SCALE must be a positive number, got '{$raw}'");
        }

        return max(min($n, self::MIN_ITERATIONS), (int) round($n * $scale));
    }
}
