<?php

declare(strict_types=1);

namespace Eram\Daynum\Exception;

/**
 * Thrown when a format token that requires timezone information is used
 * on an Instant without a timezone label.
 */
final class MissingTimezoneException extends \RuntimeException implements DaynumException
{
    public static function forToken(string $token): self
    {
        return new self(sprintf(
            'Format token "%s" requires a timezone, but none is set on this Instant. '
            . 'Use Instant::withTzLabel() or pass a timezone to the constructor.',
            $token,
        ));
    }
}
