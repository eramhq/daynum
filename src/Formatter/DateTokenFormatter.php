<?php

declare(strict_types=1);

namespace Daynum\Formatter;

/**
 * PHP `date()`-style token engine.
 *
 * Supported tokens (M1):
 *
 * | Token | Meaning                                                           |
 * |-------|-------------------------------------------------------------------|
 * | `Y`   | 4+ digit year                                                     |
 * | `y`   | 2 digit year                                                      |
 * | `m`   | Month, zero-padded (01-12)                                        |
 * | `n`   | Month, no padding (1-12)                                          |
 * | `d`   | Day of month, zero-padded                                         |
 * | `j`   | Day of month, no padding                                          |
 * | `D`   | Weekday, short form (locale-dependent)                            |
 * | `l`   | Weekday, long form (locale-dependent)                             |
 * | `F`   | Month, long form (locale/calendar-dependent)                      |
 * | `M`   | Month, short form (locale/calendar-dependent)                     |
 * | `G`   | Hour (24h), no padding                                            |
 * | `H`   | Hour (24h), zero-padded                                           |
 * | `i`   | Minute, zero-padded                                               |
 * | `s`   | Second, zero-padded                                               |
 * | `N`   | ISO weekday (Mon=1..Sun=7)                                        |
 * | `w`   | Weekday (Sun=0..Sat=6)                                            |
 * | `t`   | Number of days in the current month                               |
 * | `L`   | 1 if leap year else 0                                             |
 * | `T`   | Timezone label as stored on the instant ("" if none)              |
 * | `e`   | Timezone label as stored on the instant ("" if none)              |
 * | `a`   | am/pm                                                             |
 * | `A`   | AM/PM                                                             |
 *
 * Literal characters are passed through unchanged. Backslash (`\`) escapes the
 * next character, so `\Y` produces a literal `Y`. After the tokens are
 * substituted the result is digit-transliterated per `$ctx->digitScript`.
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
            $out = DigitTransliterator::toScript($out, $ctx->digitScript);
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
            'D' => $ctx->locale->weekdayNameShort($ctx->dayOfWeek),
            'l' => $ctx->locale->weekdayName($ctx->dayOfWeek),
            'F' => $ctx->locale->monthName($ctx->calendarName, $ctx->month),
            'M' => $ctx->locale->monthNameShort($ctx->calendarName, $ctx->month),
            'G' => (string) $ctx->hour,
            'H' => sprintf('%02d', $ctx->hour),
            'i' => sprintf('%02d', $ctx->minute),
            's' => sprintf('%02d', $ctx->second),
            'N' => (string) $ctx->dayOfWeekIso,
            'w' => (string) $ctx->dayOfWeek,
            't' => (string) $ctx->daysInMonth,
            'L' => $ctx->isLeapYear ? '1' : '0',
            'T', 'e' => $ctx->tzLabel ?? '',
            'a' => $ctx->locale->meridiem($ctx->hour >= 12, false),
            'A' => $ctx->locale->meridiem($ctx->hour >= 12, true),
            default => null,
        };
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
