<?php

declare(strict_types=1);

namespace Eram\Daynum\Tests\Unit;

use Eram\Daynum\Internal\IntMath;
use PHPUnit\Framework\TestCase;

final class IntMathTest extends TestCase
{
    /**
     * @dataProvider signTable
     */
    public function testFloorDivAndFloorMod(int $a, int $b, int $quotient, int $remainder): void
    {
        $this->assertSame($quotient, IntMath::floorDiv($a, $b));
        $this->assertSame($remainder, IntMath::floorMod($a, $b));
        $this->assertSame($a, $quotient * $b + $remainder);
    }

    /**
     * Floor semantics: the quotient rounds toward negative infinity and the
     * remainder takes the sign of the divisor (Python's `//` and `%`).
     *
     * @return iterable<string, array{int,int,int,int}>
     */
    public static function signTable(): iterable
    {
        // Inexact division, every sign combination.
        yield '+a / +b'           => [7, 2, 3, 1];
        yield '-a / +b'           => [-7, 2, -4, 1];
        yield '+a / -b'           => [7, -2, -4, -1];
        yield '-a / -b'           => [-7, -2, 3, -1];

        // Exact multiples: no adjustment, remainder zero.
        yield '+a / +b exact'     => [6, 2, 3, 0];
        yield '-a / +b exact'     => [-6, 2, -3, 0];
        yield '+a / -b exact'     => [6, -2, -3, 0];
        yield '-a / -b exact'     => [-6, -2, 3, 0];

        // Zero dividend.
        yield '0 / +b'            => [0, 5, 0, 0];
        yield '0 / -b'            => [0, -5, 0, 0];

        // |a| < |b|: the truncated quotient is 0 in every case.
        yield '+1 / +b'           => [1, 86400, 0, 1];
        yield '-1 / +b'           => [-1, 86400, -1, 86399];
        yield '+1 / -b'           => [1, -86400, -1, -86399];
        yield '-1 / -b'           => [-1, -86400, 0, -1];

        // Divisor of magnitude 1.
        yield '-7 / 1'            => [-7, 1, -7, 0];
        yield '-7 / -1'           => [-7, -1, 7, 0];
    }
}
