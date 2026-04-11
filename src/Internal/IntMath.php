<?php

declare(strict_types=1);

namespace Eram\Daynum\Internal;

/**
 * @internal
 *
 * Integer math helpers not covered by PHP's stdlib. Kept in an internal
 * namespace because these are implementation details of the calendar
 * algorithms, not a public API.
 */
final class IntMath
{
    /**
     * Floor division for integers.
     *
     * PHP's `intdiv` truncates toward zero, which disagrees with floor for
     * mixed-sign operands. All calendar JDN math assumes floor behavior.
     */
    public static function floorDiv(int $a, int $b): int
    {
        $q = intdiv($a, $b);
        $r = $a - $q * $b;
        if ($r !== 0 && ($r < 0) !== ($b < 0)) {
            $q--;
        }
        return $q;
    }
}
