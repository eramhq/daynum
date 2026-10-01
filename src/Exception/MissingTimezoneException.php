<?php

declare(strict_types=1);

namespace Eram\Daynum\Exception;

/**
 * Thrown when a format token or operation that requires timezone information
 * is used on a CivilDateTime without a timezone label.
 */
final class MissingTimezoneException extends \RuntimeException implements DaynumException
{
    public static function forToken(string $token): self
    {
        return new self(sprintf(
            'Format token "%s" requires a timezone, but none is set on this CivilDateTime. '
            . 'Use CivilDateTime::withTzLabel() or pass a timezone to the constructor.',
            $token,
        ));
    }

    public static function forOperation(string $operation): self
    {
        return new self(sprintf(
            '%s requires a timezone, but none is set on this CivilDateTime. '
            . 'Use CivilDateTime::withTzLabel() or pass a timezone when constructing it.',
            $operation,
        ));
    }
}
