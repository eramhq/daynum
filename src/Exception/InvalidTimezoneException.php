<?php

declare(strict_types=1);

namespace Eram\Daynum\Exception;

final class InvalidTimezoneException extends \RuntimeException implements DaynumException
{
    public static function forLabel(string $label): self
    {
        return new self("Invalid or unknown timezone: \"{$label}\".");
    }
}
