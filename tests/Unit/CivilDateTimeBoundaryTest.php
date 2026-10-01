<?php

declare(strict_types=1);

namespace Eram\Daynum\Tests\Unit;

use Eram\Daynum\CivilDateTime;
use Eram\Daynum\Exception\InvalidArgumentException;
use Eram\Daynum\Exception\InvalidDateException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Edge-of-range behaviour of {@see CivilDateTime}: default arguments, exact
 * error messages, comparison ties and off-by-one boundaries.
 */
final class CivilDateTimeBoundaryTest extends TestCase
{
    // ─── tryFrom*() defaults ────────────────────────────────────────

    public function testTryFromConstructorsDefaultToMidnight(): void
    {
        $this->assertSame(0, CivilDateTime::tryFromGregorian(2026, 4, 8)?->secondsOfDay);
        $this->assertSame(0, CivilDateTime::tryFromJalali(1405, 1, 19)?->secondsOfDay);
        $this->assertSame(0, CivilDateTime::tryFromHijri(1447, 10, 21)?->secondsOfDay);
        $this->assertSame(0, CivilDateTime::tryFromHijriCivil(1447, 10, 21)?->secondsOfDay);
    }

    // ─── fromArray() type errors ────────────────────────────────────

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalidArrayProvider(): iterable
    {
        yield 'missing jdn' => [
            ['secondsOfDay' => 0],
            'CivilDateTime::fromArray() requires an integer "jdn" key.',
        ];
        yield 'string jdn' => [
            ['jdn' => '2461139'],
            'CivilDateTime::fromArray() requires an integer "jdn" key.',
        ];
        yield 'string secondsOfDay' => [
            ['jdn' => 2461139, 'secondsOfDay' => '0'],
            'CivilDateTime::fromArray() "secondsOfDay" must be an int; got string.',
        ];
        yield 'float secondsOfDay' => [
            ['jdn' => 2461139, 'secondsOfDay' => 0.0],
            'CivilDateTime::fromArray() "secondsOfDay" must be an int; got float.',
        ];
        yield 'int tzLabel' => [
            ['jdn' => 2461139, 'tzLabel' => 123],
            'CivilDateTime::fromArray() "tzLabel" must be a string or null; got int.',
        ];
        yield 'array tzLabel' => [
            ['jdn' => 2461139, 'tzLabel' => ['UTC']],
            'CivilDateTime::fromArray() "tzLabel" must be a string or null; got array.',
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    #[DataProvider('invalidArrayProvider')]
    public function testFromArrayTypeErrorMessages(array $data, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);
        CivilDateTime::fromArray($data);
    }

    // ─── encodeTime() bounds ────────────────────────────────────────

    /**
     * @return iterable<string, array{int, int, int, int}>
     */
    public static function validTimeProvider(): iterable
    {
        yield 'midnight'   => [0, 0, 0, 0];
        yield 'max hour'   => [23, 0, 0, 82800];
        yield 'max minute' => [0, 59, 0, 3540];
        yield 'max second' => [0, 0, 59, 59];
        yield 'last tick'  => [23, 59, 59, 86399];
    }

    #[DataProvider('validTimeProvider')]
    public function testEncodeTimeAcceptsInclusiveBounds(int $hour, int $minute, int $second, int $expected): void
    {
        $this->assertSame($expected, CivilDateTime::fromGregorian(2026, 4, 8, $hour, $minute, $second)->secondsOfDay);
        $this->assertSame($expected, (new CivilDateTime(2461139))->withTime($hour, $minute, $second)->secondsOfDay);
    }

    /**
     * Each component is pushed one past its bound while the others keep the
     * total inside [0, 86400), so only encodeTime() itself can reject it.
     *
     * @return iterable<string, array{int, int, int}>
     */
    public static function invalidTimeProvider(): iterable
    {
        yield 'hour -1'    => [-1, 0, 0];
        yield 'hour 24'    => [24, 0, 0];
        yield 'minute -1'  => [1, -1, 0];
        yield 'minute 60'  => [0, 60, 0];
        yield 'second -1'  => [0, 1, -1];
        yield 'second 60'  => [0, 0, 60];
    }

    #[DataProvider('invalidTimeProvider')]
    public function testEncodeTimeRejectsOnePastEachBound(int $hour, int $minute, int $second): void
    {
        $message = sprintf(
            'Invalid time-of-day %02d:%02d:%02d: hour must be 0-23, minute 0-59, second 0-59.',
            $hour,
            $minute,
            $second,
        );

        try {
            CivilDateTime::fromGregorian(2026, 4, 8, $hour, $minute, $second);
            $this->fail('fromGregorian() accepted an invalid time');
        } catch (InvalidDateException $e) {
            $this->assertSame($message, $e->getMessage());
        }

        $this->expectException(InvalidDateException::class);
        $this->expectExceptionMessage($message);
        (new CivilDateTime(2461139))->withTime($hour, $minute, $second);
    }

    // ─── Comparison ties ────────────────────────────────────────────

    public function testStrictComparisonsAreFalseOnEqualValues(): void
    {
        $a = CivilDateTime::fromGregorian(2026, 4, 8, 12);
        $b = CivilDateTime::fromGregorian(2026, 4, 8, 12, 0, 0, 'UTC');

        $this->assertFalse($a->lessThan($b));
        $this->assertFalse($a->greaterThan($b));
        $this->assertTrue($a->lessThanOrEqual($b));
        $this->assertTrue($a->greaterThanOrEqual($b));
    }

    public function testMinAndMaxKeepTheFirstOnATie(): void
    {
        $first = CivilDateTime::fromGregorian(2026, 4, 8, 12, 0, 0, 'Asia/Tehran');
        $second = CivilDateTime::fromGregorian(2026, 4, 8, 12, 0, 0, 'UTC');

        $this->assertSame($first, CivilDateTime::min($first, $second));
        $this->assertSame($first, CivilDateTime::max($first, $second));
    }

    public function testBetweenBoundsInclusiveAndExclusive(): void
    {
        $lo = CivilDateTime::fromGregorian(2026, 1, 1);
        $hi = CivilDateTime::fromGregorian(2026, 12, 31);
        $mid = CivilDateTime::fromGregorian(2026, 6, 15);

        $this->assertTrue($lo->between($lo, $hi));
        $this->assertTrue($hi->between($lo, $hi));
        $this->assertTrue($hi->between($hi, $lo));
        $this->assertFalse($lo->between($lo, $hi, inclusive: false));
        $this->assertFalse($hi->between($lo, $hi, inclusive: false));
        $this->assertFalse($hi->between($hi, $lo, inclusive: false));
        $this->assertTrue($mid->between($lo, $hi, inclusive: false));
        $this->assertTrue($mid->between($hi, $lo, inclusive: false));
        $this->assertFalse($hi->addSeconds(1)->between($lo, $hi, inclusive: false));
        $this->assertFalse($lo->subSeconds(1)->between($lo, $hi, inclusive: false));
    }

    // ─── Wall-clock differences and shifts ──────────────────────────

    public function testDiffInHoursTruncatesJustBelowEachHour(): void
    {
        $a = CivilDateTime::fromGregorian(2026, 4, 8, 12);

        $this->assertSame(0, $a->addSeconds(3599)->diffInHours($a));
        $this->assertSame(0, $a->subSeconds(3599)->diffInHours($a));
        $this->assertSame(1, $a->addSeconds(7199)->diffInHours($a));
        $this->assertSame(-1, $a->subSeconds(7199)->diffInHours($a));
    }

    public function testSubHoursMovesWholeHours(): void
    {
        $d = CivilDateTime::fromGregorian(2026, 4, 8, 12, 0, 0);

        $this->assertSame('2026-04-08 11:00:00', $d->subHours(1)->gregorian()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-04-07 11:00:00', $d->subHours(25)->gregorian()->format('Y-m-d H:i:s'));
    }

    public function testShiftRollsOverExactlyAtMidnight(): void
    {
        $last = CivilDateTime::fromGregorian(2026, 4, 8, 23, 59, 59);
        $first = CivilDateTime::fromGregorian(2026, 4, 9, 0, 0, 0);

        $this->assertTrue($last->addSeconds(0)->equals($last));
        $this->assertTrue($last->addSeconds(1)->equals($first));
        $this->assertTrue($first->subSeconds(1)->equals($last));
        $this->assertSame('2026-04-09 23:59:58', $last->addSeconds(86399)->gregorian()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-04-09 23:58:59', $last->addMinutes(1439)->gregorian()->format('Y-m-d H:i:s'));
    }
}
