<?php

declare(strict_types=1);

namespace Eram\Daynum\Tests\Unit;

use Eram\Daynum\Calendar\Gregorian\GregorianView;
use Eram\Daynum\Calendar\Jalali\JalaliView;
use Eram\Daynum\Exception\DaynumException;
use Eram\Daynum\CivilDateTime;
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
        CivilDateTime::fromArray([]);
    }

    public function testFromArrayWithNonIntJdnImplementsDaynumException(): void
    {
        $this->expectException(DaynumException::class);
        CivilDateTime::fromArray(['jdn' => 'abc']);
    }

    public function testFromArrayWithNonIntSecondsOfDayImplementsDaynumException(): void
    {
        $this->expectException(DaynumException::class);
        CivilDateTime::fromArray(['jdn' => 2461139, 'secondsOfDay' => '0']);
    }

    public function testFromArrayWithNonStringTzLabelImplementsDaynumException(): void
    {
        $this->expectException(DaynumException::class);
        CivilDateTime::fromArray(['jdn' => 2461139, 'tzLabel' => 123]);
    }

    public function testOfWithUnknownDigitScriptImplementsDaynumException(): void
    {
        $this->expectException(DaynumException::class);
        $i = CivilDateTime::fromGregorian(2026, 1, 1);
        GregorianView::of($i, null, 'bogus');
    }

    public function testWithDigitsUnknownScriptImplementsDaynumException(): void
    {
        $this->expectException(DaynumException::class);
        CivilDateTime::fromGregorian(2026, 1, 1)->gregorian()->withDigits('bogus');
    }

    public function testStartOfWeekOutOfRangeImplementsDaynumException(): void
    {
        $this->expectException(DaynumException::class);
        CivilDateTime::fromGregorian(2026, 1, 1)->gregorian()->startOfWeek(0);
    }

    public function testUnknownLocaleImplementsDaynumException(): void
    {
        $this->expectException(DaynumException::class);
        CivilDateTime::fromGregorian(2026, 1, 1)->gregorian()->withLocale('zz');
    }

    /**
     * All exceptions above must also remain catchable as \InvalidArgumentException
     * for backwards compatibility.
     */
    public function testExceptionsAreStillInvalidArgumentException(): void
    {
        try {
            CivilDateTime::fromArray([]);
            $this->fail('Expected exception');
        } catch (\InvalidArgumentException $e) {
            $this->assertInstanceOf(DaynumException::class, $e);
        }
    }

    public function testFromArrayNonIntSecondsOfDayIsStillInvalidArgumentException(): void
    {
        try {
            CivilDateTime::fromArray(['jdn' => 1, 'secondsOfDay' => '0']);
            $this->fail('Expected exception');
        } catch (\InvalidArgumentException $e) {
            $this->assertInstanceOf(DaynumException::class, $e);
        }
    }

    public function testFromArrayNonStringTzLabelIsStillInvalidArgumentException(): void
    {
        try {
            CivilDateTime::fromArray(['jdn' => 1, 'tzLabel' => 123]);
            $this->fail('Expected exception');
        } catch (\InvalidArgumentException $e) {
            $this->assertInstanceOf(DaynumException::class, $e);
        }
    }
}
