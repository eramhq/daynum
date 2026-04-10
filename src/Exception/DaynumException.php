<?php

declare(strict_types=1);

namespace Daynum\Exception;

/**
 * Marker interface implemented by every exception Daynum throws.
 *
 * Catch this to catch anything from the library without catching unrelated
 * user-code exceptions.
 */
interface DaynumException extends \Throwable
{
}
