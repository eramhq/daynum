<?php

declare(strict_types=1);

namespace Eram\Daynum\Exception;

use InvalidArgumentException;

/**
 * Thrown when a date cannot be constructed because its components are invalid.
 *
 * Examples: February 30, month 13, hour 24, year outside the supported range of
 * a specific calendar.
 */
final class InvalidDateException extends InvalidArgumentException implements DaynumException
{
    public static function forComponents(
        string $calendar,
        int $year,
        int $month,
        int $day,
        string $reason
    ): self {
        return new self(sprintf(
            'Invalid %s date %d-%02d-%02d: %s',
            $calendar,
            $year,
            $month,
            $day,
            $reason
        ));
    }

    public static function forTime(int $hour, int $minute, int $second): self
    {
        return new self(sprintf(
            'Invalid time-of-day %02d:%02d:%02d: hour must be 0-23, minute 0-59, second 0-59.',
            $hour,
            $minute,
            $second
        ));
    }
}
