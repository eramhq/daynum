<?php

declare(strict_types=1);

namespace Daynum\Tests\Conformance;

use Daynum\Instant;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

/**
 * Compares Daynum Gregorian formatting against PHP's DateTimeImmutable::format()
 * for all tokens across a matrix of dates x timezones.
 *
 * Token categories:
 *   Calendar-aware:  Y y m n d j z F M t L W o S
 *   Time-aware:      G H g h i s a A
 *   Timezone-aware:  U O P Z I T c r
 *   Weekday:         N w D l
 *   Constant:        u v (always zeros — tested separately)
 */
final class PhpFormatTokenMatrixTest extends TestCase
{
    /**
     * Tokens whose output should match DateTimeImmutable::format() exactly.
     * Excludes: u/v (Daynum always returns zeros), S (locale-dependent).
     */
    private const TOKENS = [
        'Y', 'y', 'm', 'n', 'd', 'j', 'z',
        'G', 'H', 'g', 'h', 'i', 's', 'a', 'A',
        'N', 'w', 'W', 'o', 't', 'L',
        'U', 'O', 'P', 'p', 'Z', 'I', 'T', 'c', 'r', 'e',
    ];

    /**
     * Locale-dependent tokens: F, M, D, l are English in both Daynum and PHP.
     */
    private const LOCALE_TOKENS = ['F', 'M', 'D', 'l'];

    /**
     * @dataProvider dateTimezoneMatrix
     */
    public function testTokenMatchesPhp(
        int $year, int $month, int $day,
        int $hour, int $minute, int $second,
        string $tz,
    ): void {
        $dti = new DateTimeImmutable(
            sprintf('%04d-%02d-%02d %02d:%02d:%02d', $year, $month, $day, $hour, $minute, $second),
            new DateTimeZone($tz),
        );
        $instant = Instant::fromDateTime($dti);
        $view = $instant->gregorian();

        foreach (self::TOKENS as $token) {
            $expected = $dti->format($token);
            $actual = $view->format($token);
            $this->assertSame(
                $expected,
                $actual,
                "Token '{$token}' mismatch for {$year}-{$month}-{$day} {$hour}:{$minute}:{$second} {$tz}"
            );
        }

        foreach (self::LOCALE_TOKENS as $token) {
            $expected = $dti->format($token);
            $actual = $view->format($token);
            $this->assertSame(
                $expected,
                $actual,
                "Locale token '{$token}' mismatch for {$year}-{$month}-{$day} {$tz}"
            );
        }
    }

    /**
     * @return iterable<string, array{int,int,int,int,int,int,string}>
     */
    public static function dateTimezoneMatrix(): iterable
    {
        $dates = [
            'New Year midnight'        => [2026,  1,  1,  0,  0,  0],
            'Mid-year afternoon'       => [2026,  6, 15, 14, 30, 45],
            'Leap day'                 => [2024,  2, 29, 12,  0,  0],
            'Cross-year ISO week edge' => [2024, 12, 30, 23, 59, 59],
            'Sunday Jan 1'             => [2023,  1,  1,  8, 15,  0],
            'Summer solstice'          => [2026,  6, 21, 17,  0, 30],
            'Year end'                 => [2026, 12, 31, 23, 59, 59],
        ];

        $timezones = ['UTC', 'Asia/Tehran', 'America/New_York', 'Europe/London', 'Asia/Kolkata'];

        foreach ($dates as $label => [$y, $m, $d, $h, $min, $s]) {
            foreach ($timezones as $tz) {
                yield "{$label} @ {$tz}" => [$y, $m, $d, $h, $min, $s, $tz];
            }
        }
    }

    /**
     * u/v tokens: Daynum returns fixed zeros (no sub-second precision).
     */
    public function testSubSecondTokensReturnZeros(): void
    {
        $i = Instant::fromGregorian(2026, 4, 8, 14, 30, 45, 'UTC');
        $this->assertSame('000000', $i->gregorian()->format('u'));
        $this->assertSame('000', $i->gregorian()->format('v'));
    }

    /**
     * S token (ordinal suffix) matches PHP for English locale.
     */
    public function testOrdinalSuffixMatchesPhp(): void
    {
        for ($day = 1; $day <= 28; $day++) {
            $dti = new DateTimeImmutable(sprintf('2026-01-%02d', $day), new DateTimeZone('UTC'));
            $i = Instant::fromGregorian(2026, 1, $day, 0, 0, 0, 'UTC');
            $this->assertSame(
                $dti->format('S'),
                $i->gregorian()->format('S'),
                "S mismatch for day {$day}"
            );
        }
    }

    /**
     * c and r on a Jalali view must still output Gregorian.
     */
    public function testCAndROnJalaliOutputGregorian(): void
    {
        $i = Instant::fromGregorian(2026, 4, 8, 14, 30, 45, 'UTC');
        $jalaliC = $i->jalali()->format('c');
        $gregorianC = $i->gregorian()->format('c');
        $this->assertSame($gregorianC, $jalaliC);

        $jalaliR = $i->jalali()->format('r');
        $gregorianR = $i->gregorian()->format('r');
        $this->assertSame($gregorianR, $jalaliR);
    }
}
