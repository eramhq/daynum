<?php

declare(strict_types=1);

namespace Daynum\Formatter;

use Daynum\Exception\MissingTimezoneException;

/**
 * PHP `date()`-style token engine.
 *
 * Supported tokens:
 *
 * | Token | Meaning                                                           |
 * |-------|-------------------------------------------------------------------|
 * | `Y`   | 4+ digit year                                                     |
 * | `y`   | 2 digit year                                                      |
 * | `m`   | Month, zero-padded (01-12)                                        |
 * | `n`   | Month, no padding (1-12)                                          |
 * | `d`   | Day of month, zero-padded                                         |
 * | `j`   | Day of month, no padding                                          |
 * | `z`   | Day of year, 0-indexed (0-365)                                    |
 * | `D`   | Weekday, short form (locale-dependent)                            |
 * | `l`   | Weekday, long form (locale-dependent)                             |
 * | `F`   | Month, long form (locale/calendar-dependent)                      |
 * | `M`   | Month, short form (locale/calendar-dependent)                     |
 * | `G`   | Hour (24h), no padding                                            |
 * | `H`   | Hour (24h), zero-padded                                           |
 * | `g`   | Hour (12h), no padding                                            |
 * | `h`   | Hour (12h), zero-padded                                           |
 * | `i`   | Minute, zero-padded                                               |
 * | `s`   | Second, zero-padded                                               |
 * | `N`   | ISO weekday (Mon=1..Sun=7)                                        |
 * | `w`   | Weekday (Sun=0..Sat=6)                                            |
 * | `W`   | ISO 8601 week number, zero-padded (01-53)                         |
 * | `o`   | ISO 8601 week-based year (may differ from `Y` around Jan 1/Dec 31)|
 * | `t`   | Number of days in the current month                               |
 * | `L`   | 1 if leap year else 0                                             |
 * | `e`   | Timezone identifier (e.g. `Asia/Tehran`); requires timezone       |
 * | `T`   | Timezone abbreviation (e.g. `IRST`); requires timezone            |
 * | `U`   | Unix timestamp; requires timezone                                 |
 * | `O`   | UTC offset `+0330`; requires timezone                             |
 * | `P`   | UTC offset `+03:30`; requires timezone                            |
 * | `p`   | UTC offset `+03:30` or `Z` for UTC (PHP 8.0+); requires timezone |
 * | `Z`   | UTC offset in seconds; requires timezone                          |
 * | `I`   | DST flag `0`/`1`; requires timezone                               |
 * | `c`   | ISO 8601 date (always Gregorian); requires timezone               |
 * | `r`   | RFC 2822 date (always Gregorian); requires timezone               |
 * | `u`   | Microseconds (always `000000` — no sub-second precision)          |
 * | `v`   | Milliseconds (always `000` — no sub-second precision)             |
 * | `a`   | am/pm                                                             |
 * | `A`   | AM/PM                                                             |
 * | `S`   | English ordinal suffix for day of month (locale-dependent)        |
 *
 * Literal characters are passed through unchanged. Backslash (`\`) escapes the
 * next character, so `\Y` produces a literal `Y`. After the tokens are
 * substituted the result is digit-transliterated per `$ctx->digitScript`.
 *
 * Timezone-dependent tokens (`T U O P Z I c r`) wrap their output in null-byte
 * sentinels (`\x00...\x00`) so the digit transliteration pass preserves them
 * as-is — timezone offsets, Unix timestamps, and ISO/RFC strings are programmatic
 * output, not display text.
 */
final class DateTokenFormatter
{
    public static function format(string $pattern, FormatContext $ctx): string
    {
        $out = '';
        $len = strlen($pattern);

        for ($i = 0; $i < $len; $i++) {
            $ch = $pattern[$i];

            if ($ch === '\\') {
                if ($i + 1 < $len) {
                    $out .= $pattern[$i + 1];
                    $i++;
                }
                continue;
            }

            $out .= self::substituteToken($ch, $ctx) ?? $ch;
        }

        if ($ctx->digitScript !== DigitTransliterator::LATN) {
            $segments = explode("\x00", $out);
            $result = '';
            foreach ($segments as $idx => $segment) {
                $result .= ($idx % 2 === 0)
                    ? DigitTransliterator::toScript($segment, $ctx->digitScript)
                    : $segment;
            }
            $out = $result;
        } else {
            // Strip null-byte sentinels (used by timezone tokens)
            $out = str_replace("\x00", '', $out);
        }

        return $out;
    }

    private static function substituteToken(string $token, FormatContext $ctx): ?string
    {
        return match ($token) {
            'Y' => self::yearFull($ctx->year),
            'y' => self::twoDigitYear($ctx->year),
            'm' => sprintf('%02d', $ctx->month),
            'n' => (string) $ctx->month,
            'd' => sprintf('%02d', $ctx->day),
            'j' => (string) $ctx->day,
            'z' => (string) ($ctx->dayOfYear - 1),
            'D' => $ctx->locale->weekdayNameShort($ctx->dayOfWeek),
            'l' => $ctx->locale->weekdayName($ctx->dayOfWeek),
            'F' => $ctx->locale->monthName($ctx->calendarName, $ctx->month),
            'M' => $ctx->locale->monthNameShort($ctx->calendarName, $ctx->month),
            'G' => (string) $ctx->hour,
            'H' => sprintf('%02d', $ctx->hour),
            // `?: 12` keeps midnight and noon as 12 (not 0).
            'g' => (string) ($ctx->hour % 12 ?: 12),
            'h' => sprintf('%02d', $ctx->hour % 12 ?: 12),
            'i' => sprintf('%02d', $ctx->minute),
            's' => sprintf('%02d', $ctx->second),
            'N' => (string) $ctx->dayOfWeekIso,
            'w' => (string) $ctx->dayOfWeek,
            'W' => sprintf('%02d', $ctx->weekOfYear),
            'o' => self::yearFull($ctx->weekBasedYear),
            't' => (string) $ctx->daysInMonth,
            'L' => $ctx->isLeapYear ? '1' : '0',
            'e' => $ctx->tzLabel ?? throw MissingTimezoneException::forToken('e'),
            'T' => self::requireTzRaw($ctx, 'T'),
            'U' => self::requireTzRaw($ctx, 'U'),
            'O' => self::requireTzRaw($ctx, 'O'),
            'P' => self::requireTzRaw($ctx, 'P'),
            'p' => self::requireTzRaw($ctx, 'p'),
            'Z' => self::requireTzRaw($ctx, 'Z'),
            'I' => self::requireTzRaw($ctx, 'I'),
            'c' => self::requireTzRaw($ctx, 'c'),
            'r' => self::requireTzRaw($ctx, 'r'),
            'a' => $ctx->locale->meridiem($ctx->hour >= 12, false),
            'A' => $ctx->locale->meridiem($ctx->hour >= 12, true),
            'S' => $ctx->locale->ordinalSuffix($ctx->day),
            'u' => '000000',
            'v' => '000',
            default => null,
        };
    }

    /**
     * Require a DateTimeImmutable on the context and return the PHP format
     * token's output wrapped in null-byte sentinels to protect it from digit
     * transliteration.
     */
    private static function requireTzRaw(FormatContext $ctx, string $token): string
    {
        if ($ctx->tzLabel === null || $ctx->dateTimeImmutable === null) {
            throw MissingTimezoneException::forToken($token);
        }
        return "\x00" . $ctx->dateTimeImmutable->format($token) . "\x00";
    }

    /**
     * PHP `date('Y')` emits at least 4 digits, using a leading `-` for negative
     * years. Year 0 is emitted as `0000`.
     */
    private static function yearFull(int $year): string
    {
        if ($year < 0) {
            return '-' . str_pad((string) -$year, 4, '0', STR_PAD_LEFT);
        }
        return str_pad((string) $year, 4, '0', STR_PAD_LEFT);
    }

    private static function twoDigitYear(int $year): string
    {
        $mod = $year % 100;
        if ($mod < 0) {
            $mod += 100;
        }
        return sprintf('%02d', $mod);
    }
}
