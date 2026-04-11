<?php

declare(strict_types=1);

namespace Eram\Daynum\Exception;

/**
 * Thrown when a method receives an argument outside its accepted domain.
 *
 * Extends PHP's built-in InvalidArgumentException so existing catch blocks
 * that target \InvalidArgumentException still work. Implements DaynumException
 * so `catch (DaynumException)` covers every exception the library throws.
 */
final class InvalidArgumentException extends \InvalidArgumentException implements DaynumException
{
}
