<?php

declare(strict_types=1);

namespace Eram\Daynum\Tests\Unit;

use Eram\Daynum\Calendar\Gregorian\GregorianView;
use Eram\Daynum\Calendar\Hijri\Table;
use Eram\Daynum\Calendar\Jalali\JalaliView;
use Eram\Daynum\Exception\DaynumException;
use Eram\Daynum\Exception\InvalidDateException;
use Eram\Daynum\Exception\InvalidTimezoneException;
use Eram\Daynum\Exception\MissingTimezoneException;
use Eram\Daynum\Exception\ParseException;
use Eram\Daynum\Exception\UmmAlQuraOutOfRangeException;
use Eram\Daynum\Exception\WeekAtBoundaryException;
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

    // ─── Factory messages ─────────────────────────────────────────────
    // Messages are user-facing, so each factory's full text is pinned.

    public function testInvalidDateForComponentsMessage(): void
    {
        $e = InvalidDateException::forComponents('Jalali', 1403, 2, 5, 'day out of range');
        $this->assertSame('Invalid Jalali date 1403-02-05: day out of range', $e->getMessage());
    }

    public function testInvalidDateForTimeMessage(): void
    {
        $e = InvalidDateException::forTime(24, 5, 7);
        $this->assertSame(
            'Invalid time-of-day 24:05:07: hour must be 0-23, minute 0-59, second 0-59.',
            $e->getMessage(),
        );
    }

    public function testInvalidTimezoneForLabelMessage(): void
    {
        $e = InvalidTimezoneException::forLabel('Mars/Olympus');
        $this->assertSame('Invalid or unknown timezone: "Mars/Olympus".', $e->getMessage());
    }

    public function testMissingTimezoneForTokenMessage(): void
    {
        $e = MissingTimezoneException::forToken('T');
        $this->assertSame(
            'Format token "T" requires a timezone, but none is set on this CivilDateTime. '
            . 'Use CivilDateTime::withTzLabel() or pass a timezone to the constructor.',
            $e->getMessage(),
        );
    }

    public function testMissingTimezoneForOperationMessage(): void
    {
        $e = MissingTimezoneException::forOperation('toDateTimeImmutable()');
        $this->assertSame(
            'toDateTimeImmutable() requires a timezone, but none is set on this CivilDateTime. '
            . 'Use CivilDateTime::withTzLabel() or pass a timezone when constructing it.',
            $e->getMessage(),
        );
    }

    public function testParseExceptionForFormatMessage(): void
    {
        $e = ParseException::forFormat('2026-13-01', 'Y-m-d', 'month out of range');
        $this->assertSame('Cannot parse "2026-13-01" with format "Y-m-d": month out of range', $e->getMessage());
    }

    public function testUmmAlQuraForYearMessage(): void
    {
        $e = UmmAlQuraOutOfRangeException::forYear(1700, 3, 9);
        $this->assertSame(
            'Hijri Umm al-Qura date 1700-03-09 is outside the supported range (AH '
            . Table::MIN_YEAR . ' to AH ' . Table::MAX_YEAR . '). Use fromHijriCivil() for dates outside this range.',
            $e->getMessage(),
        );
    }

    public function testUmmAlQuraForJdnMessage(): void
    {
        $e = UmmAlQuraOutOfRangeException::forJdn(1948439);
        $this->assertSame(
            'JDN 1948439 is outside the supported Hijri Umm al-Qura range (AH '
            . Table::MIN_YEAR . ' to AH ' . Table::MAX_YEAR . '). Use hijriCivil() for dates outside this range.',
            $e->getMessage(),
        );
    }

    public function testWeekAtBoundaryForJdnMessageCodeAndPrevious(): void
    {
        $previous = new \RuntimeException('inner');
        $e = WeekAtBoundaryException::forJdn(1948438, $previous);
        $this->assertSame(
            "Cannot compute ISO week number: the containing week's Thursday (JDN 1948438) falls outside "
            . "this calendar's supported year range. This affects roughly the first or last 3 days of MIN_YEAR / MAX_YEAR.",
            $e->getMessage(),
        );
        $this->assertSame(0, $e->getCode());
        $this->assertSame($previous, $e->getPrevious());
        $this->assertNull(WeekAtBoundaryException::forJdn(1)->getPrevious());
    }

    public function testWeekAtBoundaryForYearMessageCodeAndPrevious(): void
    {
        $previous = new \RuntimeException('inner');
        $e = WeekAtBoundaryException::forYear(0, $previous);
        $this->assertSame(
            "Cannot compute ISO week number: the week-based year 0 falls outside this calendar's supported year range.",
            $e->getMessage(),
        );
        $this->assertSame(0, $e->getCode());
        $this->assertSame($previous, $e->getPrevious());
        $this->assertNull(WeekAtBoundaryException::forYear(0)->getPrevious());
    }
}
