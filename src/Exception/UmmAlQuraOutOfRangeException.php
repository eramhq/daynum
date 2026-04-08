<?php

declare(strict_types=1);

namespace Daynum\Exception;

use OutOfRangeException;

/**
 * Thrown when a Hijri Umm al-Qura date falls outside the bundled KACST table
 * range of AH 1318 through AH 1500 (inclusive).
 *
 * The class exists in the Daynum\Exception namespace so users can catch it by
 * type from day one — the thrower itself lands with the Umm al-Qura calendar
 * implementation in a later milestone.
 */
final class UmmAlQuraOutOfRangeException extends OutOfRangeException implements DaynumException
{
    public const MIN_YEAR = 1318;
    public const MAX_YEAR = 1500;
}
