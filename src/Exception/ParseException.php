<?php

declare(strict_types=1);

namespace Daynum\Exception;

use InvalidArgumentException;

/**
 * Thrown when {@see AbstractCalendarView::parseExact()} cannot parse the input
 * text against the given format pattern.
 */
final class ParseException extends InvalidArgumentException implements DaynumException
{
    public static function forFormat(string $text, string $format, string $reason): self
    {
        return new self(sprintf(
            'Cannot parse "%s" with format "%s": %s',
            $text,
            $format,
            $reason,
        ));
    }
}
