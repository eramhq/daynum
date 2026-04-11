<?php

declare(strict_types=1);

namespace Daynum\Tests\Unit;

use Daynum\Calendar\Gregorian\GregorianView;
use Daynum\Calendar\Jalali\JalaliView;
use Daynum\Exception\DaynumException;
use Daynum\Instant;
use PHPUnit\Framework\TestCase;

/**
 * Verifies that every exception thrown by Daynum implements DaynumException,
 * so `catch (DaynumException $e)` never lets a library exception leak through.
 */
final class DaynumExceptionContractTest extends TestCase
{
    public function testFromArrayWithMissingJdnImplementsDaynumException(): void
    {
        $this->expectException(DaynumException::class);
        Instant::fromArray([]);
    }

    public function testFromArrayWithNonIntJdnImplementsDaynumException(): void
    {
        $this->expectException(DaynumException::class);
        Instant::fromArray(['jdn' => 'abc']);
    }

    public function testFromArrayWithNonIntSecondsOfDayImplementsDaynumException(): void
    {
        $this->expectException(DaynumException::class);
        Instant::fromArray(['jdn' => 2461139, 'secondsOfDay' => '0']);
    }

    public function testFromArrayWithNonStringTzLabelImplementsDaynumException(): void
    {
        $this->expectException(DaynumException::class);
        Instant::fromArray(['jdn' => 2461139, 'tzLabel' => 123]);
    }

    public function testOfWithUnknownDigitScriptImplementsDaynumException(): void
    {
        $this->expectException(DaynumException::class);
        $i = Instant::fromGregorian(2026, 1, 1);
        GregorianView::of($i, null, 'bogus');
    }

    public function testWithDigitsUnknownScriptImplementsDaynumException(): void
    {
        $this->expectException(DaynumException::class);
        Instant::fromGregorian(2026, 1, 1)->gregorian()->withDigits('bogus');
    }

    public function testStartOfWeekOutOfRangeImplementsDaynumException(): void
    {
        $this->expectException(DaynumException::class);
        Instant::fromGregorian(2026, 1, 1)->gregorian()->startOfWeek(0);
    }

    public function testUnknownLocaleImplementsDaynumException(): void
    {
        $this->expectException(DaynumException::class);
        Instant::fromGregorian(2026, 1, 1)->gregorian()->withLocale('zz');
    }

    /**
     * All exceptions above must also remain catchable as \InvalidArgumentException
     * for backwards compatibility.
     */
    public function testExceptionsAreStillInvalidArgumentException(): void
    {
        try {
            Instant::fromArray([]);
            $this->fail('Expected exception');
        } catch (\InvalidArgumentException $e) {
            $this->assertInstanceOf(DaynumException::class, $e);
        }
    }

    public function testFromArrayNonIntSecondsOfDayIsStillInvalidArgumentException(): void
    {
        try {
            Instant::fromArray(['jdn' => 1, 'secondsOfDay' => '0']);
            $this->fail('Expected exception');
        } catch (\InvalidArgumentException $e) {
            $this->assertInstanceOf(DaynumException::class, $e);
        }
    }

    public function testFromArrayNonStringTzLabelIsStillInvalidArgumentException(): void
    {
        try {
            Instant::fromArray(['jdn' => 1, 'tzLabel' => 123]);
            $this->fail('Expected exception');
        } catch (\InvalidArgumentException $e) {
            $this->assertInstanceOf(DaynumException::class, $e);
        }
    }
}
