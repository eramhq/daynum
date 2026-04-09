<?php

declare(strict_types=1);

namespace Daynum\Exception;

use RuntimeException;
use Throwable;

/**
 * Thrown when ISO week computation reaches a calendar's MIN_YEAR / MAX_YEAR
 * boundary and the containing week's Thursday — or the resulting week-based
 * year — falls outside the supported range.
 *
 * The ISO 8601 week of the first or last few days of a bounded calendar can
 * legitimately belong to a notional year just before MIN_YEAR or just after
 * MAX_YEAR (e.g. AH 1 Muharram 1 is a Friday whose containing week's Thursday
 * is the day before the HijriCivil epoch). Rather than returning a misleading
 * sentinel that silently collides with other weeks, Daynum throws so callers
 * at the edge can decide what to do.
 */
final class WeekAtBoundaryException extends RuntimeException implements DaynumException
{
    public static function forJdn(int $thursdayJdn, ?Throwable $previous = null): self
    {
        return new self(
            sprintf(
                "Cannot compute ISO week number: the containing week's Thursday "
                . "(JDN %d) falls outside this calendar's supported year range. "
                . 'This affects roughly the first or last 3 days of MIN_YEAR / MAX_YEAR.',
                $thursdayJdn,
            ),
            0,
            $previous,
        );
    }

    public static function forYear(int $thursdayYear, ?Throwable $previous = null): self
    {
        return new self(
            sprintf(
                'Cannot compute ISO week number: the week-based year %d '
                . "falls outside this calendar's supported year range.",
                $thursdayYear,
            ),
            0,
            $previous,
        );
    }
}
