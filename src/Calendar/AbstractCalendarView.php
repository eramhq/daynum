<?php

declare(strict_types=1);

namespace Eram\Daynum\Calendar;

use Eram\Daynum\Calendar;
use Eram\Daynum\CalendarView;
use Eram\Daynum\Exception\DaynumException;
use Eram\Daynum\Exception\InvalidArgumentException;
use Eram\Daynum\Exception\InvalidDateException;
use Eram\Daynum\Exception\ParseException;
use Eram\Daynum\Exception\WeekAtBoundaryException;
use Eram\Daynum\Formatter\DateTokenFormatter;
use Eram\Daynum\Formatter\DigitTransliterator;
use Eram\Daynum\Formatter\FormatContext;
use Eram\Daynum\CivilDateTime;
use Eram\Daynum\Internal\NameNormalizer;
use Eram\Daynum\Locale\LocaleData;
use Eram\Daynum\Locale\LocaleRegistry;
use Eram\Daynum\WeekDay;

/**
 * Shared implementation of {@see CalendarView} for calendars whose arithmetic
 * can be expressed entirely through the {@see Calendar} interface.
 *
 * Subclasses only need to supply their backing calendar, their default format
 * pattern, and their default locale. Everything else — component accessors,
 * formatting, arithmetic, diffs — is implemented here in terms of the
 * {@see Calendar} methods.
 *
 * Subclasses are final and immutable; `withLocale` / `withDigits` return a
 * new instance of the same concrete type.
 */
abstract class AbstractCalendarView implements CalendarView
{
    /**
     * @var array{year:int, month:int, day:int, daysInMonth:int, isLeapYear:bool}|null
     */
    private ?array $cache = null;

    final protected function __construct(
        protected readonly CivilDateTime $dateTime,
        protected readonly LocaleData $locale,
        protected readonly string $digitScript,
    ) {}

    abstract public function calendar(): Calendar;

    /**
     * Return the calendar singleton without constructing a view.
     * Used by {@see parseExact()} which is static and has no instance.
     */
    abstract protected static function calendarInstance(): Calendar;

    /** Default format pattern used by `__toString()`. */
    abstract protected function defaultFormat(): string;

    // ─── Instance factory (used by CivilDateTime) ───────────────────────────

    /**
     * @return static
     */
    public static function of(CivilDateTime $dateTime, ?string $locale = null, string $digitScript = DigitTransliterator::LATN): static
    {
        if (!DigitTransliterator::isSupported($digitScript)) {
            throw new InvalidArgumentException("Unknown digit script '{$digitScript}'.");
        }
        return new static($dateTime, LocaleRegistry::get($locale ?? 'en'), $digitScript);
    }

    // ─── Parsing ──────────────────────────────────────────────────────

    /**
     * Tokens that can be parsed: each maps to a field name.
     *
     * Fixed-width tokens (m, d, H, h, i, s) consume exactly 2 digits.
     * Variable-width tokens (n, j, G, g) consume 1-2 digits greedily.
     * Name tokens (F, M, l, D) match the parse locale's month/weekday names.
     */
    private const PARSE_TOKENS = [
        'Y' => 'year',
        'm' => 'month',
        'n' => 'month',
        'F' => 'month',
        'M' => 'month',
        'l' => 'weekday',
        'D' => 'weekday',
        'd' => 'day',
        'j' => 'day',
        'H' => 'hour',
        'G' => 'hour',
        'h' => 'hour12',
        'g' => 'hour12',
        'i' => 'minute',
        's' => 'second',
        'a' => 'meridiem',
        'A' => 'meridiem',
        'P' => 'tzOffsetP',
        'p' => 'tzOffsetP',
        'O' => 'tzOffsetO',
    ];

    /**
     * Parse a date/time string in the given format, returning a CivilDateTime.
     *
     * Supported tokens:
     * `Y` (4+ digit year), `m`/`n` (month), `d`/`j` (day),
     * `H`/`G` (hour 24h), `h`/`g` (hour 12h), `i` (minute),
     * `s` (second), `a`/`A` (AM/PM in `$locale`, or am/pm, ق.ظ/ب.ظ, ص/م),
     * `P`/`p` (UTC offset `+HH:MM` or `Z`), `O` (UTC offset `+HHMM`),
     * `c` (ISO 8601 composite: `Y-m-d\TH:i:sP`),
     * `F`/`M` (month name, full or short, in `$locale`),
     * `l`/`D` (weekday name, full or short, in `$locale`; must agree with
     * the parsed date).
     *
     * Variable-width tokens (`n`, `j`, `G`, `g`) consume 1-2 digits
     * greedily and must be followed by a literal separator, not another
     * token.
     *
     * Digits in any script (Persian U+06F0, Arabic-Indic U+0660) are
     * normalized to ASCII before parsing. Names match case-insensitively
     * (ASCII, Latin-1 and Turkish letters; İ, I and ı all match) and treat
     * Arabic ي/ك as Persian ی/ک, ignoring ZWNJ and the ezafe hamza (ٔ).
     *
     * @param ?string $locale tag whose month/weekday names `F M l D` and
     *                        AM/PM markers `a A` match;
     *                        defaults to `en`, like views
     *
     * @throws ParseException on format mismatch, unsupported tokens, or invalid date
     * @throws InvalidArgumentException on an unknown locale tag
     */
    public static function parseExact(
        string $text,
        string $format,
        ?string $tzLabel = null,
        ?string $locale = null,
    ): CivilDateTime {
        $localeData = LocaleRegistry::get($locale ?? 'en');

        // Normalize non-Latin digits to ASCII
        $text = DigitTransliterator::toLatin($text);

        $parts = self::tokenizeFormat($format);
        $pos = 0;
        $fields = [];
        $isPm = null;
        $tzOffset = null;

        $partCount = count($parts);
        foreach ($parts as $idx => $part) {
            if ($part['type'] === 'literal') {
                $literal = $part['value'];
                $len = strlen($literal);
                if (substr($text, $pos, $len) !== $literal) {
                    throw ParseException::forFormat(
                        $text,
                        $format,
                        sprintf('expected literal "%s" at position %d', $literal, $pos),
                    );
                }
                $pos += $len;
                continue;
            }

            // Token extraction
            $token = $part['value'];
            $fieldName = self::PARSE_TOKENS[$token] ?? null;
            if ($fieldName === null) {
                throw ParseException::forFormat(
                    $text,
                    $format,
                    sprintf('token "%s" is not supported for parsing (format-only)', $token),
                );
            }

            if ($token === 'Y') {
                // When the next part is another token (no literal separator),
                // limit year to exactly 4 digits to avoid greedily consuming
                // the next field's digits.
                $nextIsToken = ($idx + 1 < $partCount && $parts[$idx + 1]['type'] === 'token');
                $extracted = self::extractYear($text, $pos, $nextIsToken);
                if ($extracted === null) {
                    throw ParseException::forFormat($text, $format, "expected year at position {$pos}");
                }
                self::setField($fields, 'year', $extracted['value'], $text, $format);
                $pos = $extracted['end'];
            } elseif ($token === 'a' || $token === 'A') {
                $extracted = self::extractMeridiem($text, $pos, $localeData);
                if ($extracted === null) {
                    throw ParseException::forFormat($text, $format, "expected am/pm at position {$pos}");
                }
                $isPm = $extracted['value'];
                $pos = $extracted['end'];
            } elseif ($fieldName === 'weekday' || $token === 'F' || $token === 'M') {
                $names = $fieldName === 'weekday'
                    ? self::weekdayNames($localeData)
                    : self::monthNames($localeData, static::calendarInstance()->localeFamily());
                if ($names === null) {
                    throw ParseException::forFormat($text, $format, sprintf(
                        'locale "%s" has no %s month names',
                        $localeData->tag(),
                        static::calendarInstance()->localeFamily(),
                    ));
                }
                $extracted = self::extractName($text, $pos, $names);
                if ($extracted === null) {
                    throw ParseException::forFormat($text, $format, sprintf(
                        'expected a %s name for "%s" at position %d',
                        $fieldName === 'weekday' ? 'weekday' : 'month',
                        $token,
                        $pos,
                    ));
                }
                self::setField($fields, $fieldName, $extracted['value'], $text, $format);
                $pos = $extracted['end'];
            } elseif ($token === 'n' || $token === 'j' || $token === 'G' || $token === 'g') {
                // Variable-width (1-2 digit) tokens
                $nextPart = $parts[$idx + 1] ?? null;
                $nextIsToken = $nextPart !== null && $nextPart['type'] === 'token';
                if ($nextIsToken) {
                    throw ParseException::forFormat(
                        $text,
                        $format,
                        sprintf('variable-width token "%s" cannot be followed directly by another token without a literal separator', $token),
                    );
                }
                $extracted = self::extractVariableWidth($text, $pos);
                if ($extracted === null) {
                    throw ParseException::forFormat(
                        $text,
                        $format,
                        sprintf('expected 1-2 digits for "%s" at position %d', $token, $pos),
                    );
                }
                self::setField($fields, $fieldName, $extracted['value'], $text, $format);
                $pos = $extracted['end'];
            } elseif ($token === 'P' || $token === 'p') {
                $extracted = self::extractTzOffset($text, $pos, '/^[+-]\d{2}:\d{2}/', allowZ: true);
                if ($extracted === null) {
                    throw ParseException::forFormat(
                        $text,
                        $format,
                        sprintf('expected timezone offset (+HH:MM or Z) for "%s" at position %d', $token, $pos),
                    );
                }
                $tzOffset = $extracted['value'];
                $pos = $extracted['end'];
            } elseif ($token === 'O') {
                $extracted = self::extractTzOffset($text, $pos, '/^[+-]\d{4}/');
                if ($extracted === null) {
                    throw ParseException::forFormat(
                        $text,
                        $format,
                        sprintf('expected timezone offset (+HHMM) for "O" at position %d', $pos),
                    );
                }
                $tzOffset = substr_replace($extracted['value'], ':', 3, 0);   // +HHMM → +HH:MM
                $pos = $extracted['end'];
            } else {
                // Fixed 2-digit numeric token
                if ($pos + 2 > strlen($text)) {
                    throw ParseException::forFormat(
                        $text,
                        $format,
                        sprintf('expected 2 digits for "%s" at position %d, but input is too short', $token, $pos),
                    );
                }
                $digits = substr($text, $pos, 2);
                if (!ctype_digit($digits)) {
                    throw ParseException::forFormat(
                        $text,
                        $format,
                        sprintf('expected 2 digits for "%s" at position %d, got "%s"', $token, $pos, $digits),
                    );
                }
                self::setField($fields, $fieldName, (int) $digits, $text, $format);
                $pos += 2;
            }
        }

        if ($pos !== strlen($text)) {
            throw ParseException::forFormat(
                $text,
                $format,
                sprintf('trailing input after position %d: "%s"', $pos, substr($text, $pos)),
            );
        }

        // Resolve 12-hour to 24-hour
        if (isset($fields['hour12'])) {
            if ($isPm === null) {
                throw ParseException::forFormat(
                    $text,
                    $format,
                    'h token (12h) requires a/A meridiem token to resolve ambiguity',
                );
            }
            $h12 = $fields['hour12'];
            if ($h12 < 1 || $h12 > 12) {
                throw ParseException::forFormat($text, $format, "12-hour value {$h12} out of range 1-12");
            }
            $fields['hour'] = self::resolve12Hour($h12, $isPm);
            unset($fields['hour12']);
        }

        // Defaults
        $year = $fields['year'] ?? null;
        $month = $fields['month'] ?? null;
        $day = $fields['day'] ?? null;

        if ($year === null || $month === null || $day === null) {
            throw ParseException::forFormat(
                $text,
                $format,
                'format must include at least Y, m/n, and d/j tokens',
            );
        }

        $hour = $fields['hour'] ?? 0;
        $minute = $fields['minute'] ?? 0;
        $second = $fields['second'] ?? 0;

        if ($hour > 23 || $minute > 59 || $second > 59) {
            throw ParseException::forFormat($text, $format, sprintf(
                'time component out of range: hour=%d, minute=%d, second=%d',
                $hour,
                $minute,
                $second,
            ));
        }

        // A parsed timezone offset overrides the $tzLabel parameter
        $parsedTz = $tzOffset ?? $tzLabel;

        // Validate via the calendar's toJdn (reuses all existing validation)
        $calendar = static::calendarInstance();

        try {
            $jdn = $calendar->toJdn($year, $month, $day);
        } catch (DaynumException $e) {
            throw ParseException::forFormat($text, $format, $e->getMessage());
        }

        if (isset($fields['weekday'])) {
            $actual = (($jdn + 1) % 7 + 7) % 7;   // PHP `w`: Sunday = 0
            if ($actual !== $fields['weekday']) {
                throw ParseException::forFormat($text, $format, sprintf(
                    'weekday "%s" does not match the date, which is a %s',
                    $localeData->weekdayName($fields['weekday']),
                    $localeData->weekdayName($actual),
                ));
            }
        }

        try {
            return new CivilDateTime($jdn, $hour * 3600 + $minute * 60 + $second, $parsedTz);
        } catch (\Eram\Daynum\Exception\InvalidDateException $e) {
            throw ParseException::forFormat($text, $format, $e->getMessage());
        }
    }

    /**
     * Break a format string into a sequence of literal and token parts.
     *
     * @return list<array{type: 'literal'|'token', value: string}>
     */
    private static function tokenizeFormat(string $format): array
    {
        $parts = [];
        $len = strlen($format);
        $literal = '';

        for ($i = 0; $i < $len; $i++) {
            $ch = $format[$i];

            if ($ch === '\\') {
                // Escaped character → literal
                if ($i + 1 < $len) {
                    $literal .= $format[$i + 1];
                    $i++;
                }
                continue;
            }

            // Composite token expansion: `c` → `Y-m-d\TH:i:sP`
            if ($ch === 'c') {
                if ($literal !== '') {
                    $parts[] = ['type' => 'literal', 'value' => $literal];
                    $literal = '';
                }
                // Expand inline — the `\T` becomes a literal "T" separator
                $parts[] = ['type' => 'token', 'value' => 'Y'];
                $parts[] = ['type' => 'literal', 'value' => '-'];
                $parts[] = ['type' => 'token', 'value' => 'm'];
                $parts[] = ['type' => 'literal', 'value' => '-'];
                $parts[] = ['type' => 'token', 'value' => 'd'];
                $parts[] = ['type' => 'literal', 'value' => 'T'];
                $parts[] = ['type' => 'token', 'value' => 'H'];
                $parts[] = ['type' => 'literal', 'value' => ':'];
                $parts[] = ['type' => 'token', 'value' => 'i'];
                $parts[] = ['type' => 'literal', 'value' => ':'];
                $parts[] = ['type' => 'token', 'value' => 's'];
                $parts[] = ['type' => 'token', 'value' => 'P'];
                continue;
            }

            if (isset(self::PARSE_TOKENS[$ch]) || self::isFormatOnlyToken($ch)) {
                if ($literal !== '') {
                    $parts[] = ['type' => 'literal', 'value' => $literal];
                    $literal = '';
                }
                $parts[] = ['type' => 'token', 'value' => $ch];
            } else {
                $literal .= $ch;
            }
        }

        if ($literal !== '') {
            $parts[] = ['type' => 'literal', 'value' => $literal];
        }

        return $parts;
    }

    /** Tokens recognized by the formatter but NOT supported for parsing. */
    private const FORMAT_ONLY_TOKENS = [
        'y' => true, 'z' => true,
        'N' => true, 'w' => true,
        'W' => true, 'o' => true, 't' => true, 'L' => true,
        'T' => true, 'e' => true, 'S' => true,
        'u' => true, 'v' => true,
        'U' => true,
        'Z' => true, 'I' => true, 'c' => true, 'r' => true,
    ];

    private static function isFormatOnlyToken(string $ch): bool
    {
        return isset(self::FORMAT_ONLY_TOKENS[$ch]);
    }

    /**
     * Extract a year value (optional `-` prefix, then 4+ digits).
     *
     * When $fixedWidth is true (next part is a token with no separator),
     * exactly 4 digits are consumed. Otherwise, all consecutive digits
     * are consumed (to handle years > 9999 when delimited).
     *
     * @return array{value: int, end: int}|null
     */
    private static function extractYear(string $text, int $pos, bool $fixedWidth): ?array
    {
        $negative = false;
        $p = $pos;

        if ($p < strlen($text) && $text[$p] === '-') {
            $negative = true;
            $p++;
        }

        if ($fixedWidth) {
            // Exactly 4 digits
            if ($p + 4 > strlen($text)) {
                return null;
            }
            $digits = substr($text, $p, 4);
            if (!ctype_digit($digits)) {
                return null;
            }
            $value = (int) $digits;
            $p += 4;
        } else {
            $start = $p;
            while ($p < strlen($text) && ctype_digit($text[$p])) {
                $p++;
            }
            if ($p - $start < 4) {
                return null;
            }
            $value = (int) substr($text, $start, $p - $start);
        }

        if ($negative) {
            $value = -$value;
        }

        return ['value' => $value, 'end' => $p];
    }

    /**
     * AM/PM markers every parse locale accepts, on top of its own: Latin
     * am/pm, Persian ق.ظ/ب.ظ and Arabic ص/م. Value 1 means PM.
     */
    private const MERIDIEM_FALLBACKS = [
        ['am', 0], ['pm', 1],
        ['ق.ظ', 0], ['ب.ظ', 1],
        ['ص', 0], ['م', 1],
    ];

    /**
     * Extract an AM/PM marker: the parse locale's own (either case), or one
     * of {@see MERIDIEM_FALLBACKS}. Matches like names: case-insensitive,
     * longest first, ending at a word boundary.
     *
     * @return array{value: bool, end: int}|null  value = true for PM
     */
    private static function extractMeridiem(string $text, int $pos, LocaleData $locale): ?array
    {
        $markers = self::MERIDIEM_FALLBACKS;
        foreach ([false, true] as $isPm) {
            foreach ([false, true] as $uppercase) {
                $markers[] = [NameNormalizer::normalize($locale->meridiem($isPm, $uppercase)), (int) $isPm];
            }
        }

        $extracted = self::extractName($text, $pos, self::longestFirst($markers));
        return $extracted === null
            ? null
            : ['value' => $extracted['value'] === 1, 'end' => $extracted['end']];
    }

    /**
     * Extract a 1-or-2 digit value for variable-width tokens (n, j, G, g).
     *
     * @return array{value: int, end: int}|null
     */
    private static function extractVariableWidth(string $text, int $pos): ?array
    {
        if ($pos >= strlen($text) || !ctype_digit($text[$pos])) {
            return null;
        }
        // Greedy: take 2 digits if available
        if ($pos + 1 < strlen($text) && ctype_digit($text[$pos + 1])) {
            return ['value' => (int) substr($text, $pos, 2), 'end' => $pos + 2];
        }
        return ['value' => (int) $text[$pos], 'end' => $pos + 1];
    }

    /**
     * Extract a timezone offset matching `$regex`, with optional `Z` support.
     *
     * @return array{value: string, end: int}|null
     */
    private static function extractTzOffset(string $text, int $pos, string $regex, bool $allowZ = false): ?array
    {
        if ($allowZ && $pos < strlen($text) && $text[$pos] === 'Z') {
            return ['value' => '+00:00', 'end' => $pos + 1];
        }
        if (preg_match($regex, substr($text, $pos), $m)) {
            $raw = $m[0];
            // Real-world IANA range: -12:00 (Baker Island) to +14:00 (Kiribati)
            $sign = $raw[0];
            $h = (int) substr($raw, 1, 2);
            $min = (int) substr($raw, -2);   // +HH:MM and +HHMM both end in MM
            $maxH = $sign === '-' ? 12 : 14;
            if ($h > $maxH || $min > 59 || ($h === $maxH && $min !== 0)) {
                return null;
            }
            return ['value' => $raw, 'end' => $pos + strlen($raw)];
        }
        return null;
    }

    /**
     * Convert 12-hour + PM flag to 24-hour value.
     */
    private static function resolve12Hour(int $hour12, bool $isPm): int
    {
        if ($hour12 === 12) {
            return $isPm ? 12 : 0;
        }
        return $isPm ? $hour12 + 12 : $hour12;
    }

    /**
     * Store a parsed field, rejecting a second token that disagrees with the
     * first (e.g. `F` and `m` naming different months).
     *
     * @param array<string, int> $fields
     */
    private static function setField(array &$fields, string $name, int $value, string $text, string $format): void
    {
        if (isset($fields[$name]) && $fields[$name] !== $value) {
            throw ParseException::forFormat($text, $format, sprintf(
                'conflicting values for %s: %d and %d',
                $name,
                $fields[$name],
                $value,
            ));
        }
        $fields[$name] = $value;
    }

    /** @var \WeakMap<LocaleData, array<string, list<array{string, int}>|null>>|null */
    private static ?\WeakMap $nameCache = null;

    /**
     * Long and short month names of a locale family as [normalized name,
     * month] pairs, longest first; null when the locale lacks the family.
     *
     * @return list<array{string, int}>|null
     */
    private static function monthNames(LocaleData $locale, string $family): ?array
    {
        $cache = self::$nameCache ??= new \WeakMap();
        $entry = $cache[$locale] ?? [];
        if (!array_key_exists($family, $entry)) {
            $names = [];
            try {
                // 13 leaves room for calendars with a leap month.
                for ($m = 1; $m <= 13; $m++) {
                    $names[] = [NameNormalizer::normalize($locale->monthName($family, $m)), $m];
                    $names[] = [NameNormalizer::normalize($locale->monthNameShort($family, $m)), $m];
                }
            } catch (InvalidArgumentException) {
                // Unknown family (first call) or past the last month.
            }
            $entry[$family] = $names === [] ? null : self::longestFirst($names);
            $cache[$locale] = $entry;
        }
        return $entry[$family];
    }

    /**
     * Long and short weekday names as [normalized name, PHP `w`] pairs.
     *
     * @return list<array{string, int}>
     */
    private static function weekdayNames(LocaleData $locale): array
    {
        $cache = self::$nameCache ??= new \WeakMap();
        $entry = $cache[$locale] ?? [];
        if (!isset($entry["\0weekday"])) {
            $names = [];
            for ($w = 0; $w <= 6; $w++) {
                $names[] = [NameNormalizer::normalize($locale->weekdayName($w)), $w];
                $names[] = [NameNormalizer::normalize($locale->weekdayNameShort($w)), $w];
            }
            $entry["\0weekday"] = self::longestFirst($names);
            $cache[$locale] = $entry;
        }
        return $entry["\0weekday"];
    }

    /**
     * @param list<array{string, int}> $names
     * @return list<array{string, int}>
     */
    private static function longestFirst(array $names): array
    {
        $names = array_values(array_unique($names, SORT_REGULAR));
        usort($names, static fn(array $a, array $b): int => strlen($b[0]) <=> strlen($a[0]));
        return $names;
    }

    /**
     * Match the longest name at `$pos` that ends at a word boundary,
     * comparing normalized forms and mapping the match back to a byte
     * offset in the original text.
     *
     * @param list<array{string, int}> $names normalized, longest first
     * @return array{value: int, end: int}|null
     */
    private static function extractName(string $text, int $pos, array $names): ?array
    {
        $maxLength = strlen($names[0][0] ?? '');
        // Normalize the input one UTF-8 character at a time, recording where
        // each normalized length ends in the original text.
        $normalized = '';
        $endAt = [];
        $offset = $pos;
        $length = strlen($text);
        while ($offset < $length && strlen($normalized) < $maxLength) {
            $charLength = self::utf8CharLength($text, $offset);
            $normalized .= NameNormalizer::normalize(substr($text, $offset, $charLength));
            $offset += $charLength;
            $endAt[strlen($normalized)] = $offset;
        }

        foreach ($names as [$name, $value]) {
            if ($name !== '' && str_starts_with($normalized, $name) && isset($endAt[strlen($name)])) {
                $end = $endAt[strlen($name)];
                // Swallow trailing characters that normalize away (ZWNJ, ezafe).
                while ($end < $length) {
                    $charLength = self::utf8CharLength($text, $end);
                    if (NameNormalizer::normalize(substr($text, $end, $charLength)) !== '') {
                        break;
                    }
                    $end += $charLength;
                }
                // A name must end at a word boundary: "Aprl" is not "Apr" + "l".
                if ($end < $length
                    && preg_match('/\p{L}/u', substr($text, $end, self::utf8CharLength($text, $end))) === 1
                ) {
                    continue;
                }
                return ['value' => $value, 'end' => $end];
            }
        }
        return null;
    }

    private static function utf8CharLength(string $text, int $offset): int
    {
        $byte = ord($text[$offset]);
        return match (true) {
            $byte >= 0xF0 => 4,
            $byte >= 0xE0 => 3,
            $byte >= 0xC0 => 2,
            default => 1,
        };
    }

    /**
     * Try to parse the given text; return null instead of throwing.
     *
     * Catches {@see ParseException} only — this covers all failure modes
     * because {@see parseExact()} already wraps calendar-level exceptions
     * (InvalidDateException, UmmAlQuraOutOfRangeException) in ParseException.
     */
    public static function tryParseExact(
        string $text,
        string $format,
        ?string $tzLabel = null,
        ?string $locale = null,
    ): ?CivilDateTime {
        try {
            return static::parseExact($text, $format, $tzLabel, $locale);
        } catch (ParseException) {
            return null;
        }
    }

    // ─── CalendarView interface ───────────────────────────────────────

    public function dateTime(): CivilDateTime
    {
        return $this->dateTime;
    }

    public function year(): int
    {
        return $this->components()['year'];
    }

    public function month(): int
    {
        return $this->components()['month'];
    }

    public function day(): int
    {
        return $this->components()['day'];
    }

    public function hour(): int
    {
        return intdiv($this->dateTime->secondsOfDay, 3600);
    }

    public function minute(): int
    {
        return intdiv($this->dateTime->secondsOfDay % 3600, 60);
    }

    public function second(): int
    {
        return $this->dateTime->secondsOfDay % 60;
    }

    public function dayOfWeek(): int
    {
        // JDN 0 was a Monday. PHP's w is Sun=0..Sat=6.
        // JDN 0 (Mon) → w=1. Formula: ((jdn + 1) mod 7), normalized.
        $w = ($this->dateTime->jdn + 1) % 7;
        if ($w < 0) {
            $w += 7;
        }
        return $w;
    }

    public function dayOfWeekIso(): int
    {
        // Monday = 1, Sunday = 7.
        $iso = $this->dateTime->jdn % 7;
        if ($iso < 0) {
            $iso += 7;
        }
        return $iso + 1;
    }

    public function dayOfYear(): int
    {
        $c = $this->components();
        return $this->calendar()->dayOfYear($c['year'], $c['month'], $c['day']);
    }

    public function weekOfYear(): int
    {
        // ISO 8601 week number. A week belongs to the year of its Thursday,
        // and week 1 is the one holding that year's first Thursday, so the
        // Thursday's 0-based day of year divided by 7 is the 0-based week.
        $jdn = $this->dateTime->jdn;
        $isoDow = $this->dayOfWeekIso();           // Mon=1..Sun=7
        $thursdayJdn = $jdn - $isoDow + 4;          // JDN of this week's Thursday
        $calendar = $this->calendar();

        try {
            [$thursdayYear, , ] = $calendar->fromJdn($thursdayJdn);
            $yearStart = $calendar->toJdn($thursdayYear, 1, 1);
        } catch (DaynumException $e) {
            // A sentinel `1` would collide with the real week 1 at the MIN
            // edge and be indistinguishable from the current year's week 1
            // at the MAX edge — throwing is the only non-misleading outcome.
            throw WeekAtBoundaryException::forJdn($thursdayJdn, $e);
        }

        return intdiv($thursdayJdn - $yearStart, 7) + 1;
    }

    public function weekBasedYear(): int
    {
        // ISO 8601 week-based year — the year owning the ISO week of this
        // date's Thursday. Differs from `year()` by ±1 around Jan 1 / Dec 31.
        $jdn = $this->dateTime->jdn;
        $isoDow = $this->dayOfWeekIso();
        $thursdayJdn = $jdn - $isoDow + 4;
        $calendar = $this->calendar();

        try {
            [$thursdayYear, , ] = $calendar->fromJdn($thursdayJdn);
        } catch (DaynumException $e) {
            throw WeekAtBoundaryException::forJdn($thursdayJdn, $e);
        }

        // When the Thursday falls in the view's own year, the year is
        // known-valid by construction. Only early-Jan / late-Dec dates
        // (roughly 4/365) name an adjacent year, and closed-form `fromJdn`
        // implementations (HijriCivil, Jalali, Gregorian) can return a
        // notional out-of-range one that only `toJdn` would reject. Validate
        // it by attempting Jan 4: any year ISO week 1 references must itself
        // be a valid year of this calendar.
        if ($thursdayYear !== $this->components()['year']) {
            try {
                $calendar->toJdn($thursdayYear, 1, 4);
            } catch (DaynumException $e) {
                throw WeekAtBoundaryException::forYear($thursdayYear, $e);
            }
        }

        return $thursdayYear;
    }

    public function isLeapYear(): bool
    {
        return $this->components()['isLeapYear'];
    }

    public function daysInMonth(): int
    {
        return $this->components()['daysInMonth'];
    }

    public function daysInYear(): int
    {
        $calendar = $this->calendar();
        $year = $this->year();
        $total = 0;
        $monthsInYear = $calendar->monthsInYear($year);
        for ($m = 1; $m <= $monthsInYear; $m++) {
            $total += $calendar->daysInMonth($year, $m);
        }
        return $total;
    }

    // ─── Serialization ─────────────────────────────────────────────────

    /**
     * Calendar-specific array representation.
     *
     * @return array{year: int, month: int, day: int, hour: int, minute: int, second: int, tzLabel: ?string}
     */
    public function toArray(): array
    {
        return [
            'year' => $this->year(),
            'month' => $this->month(),
            'day' => $this->day(),
            'hour' => $this->hour(),
            'minute' => $this->minute(),
            'second' => $this->second(),
            'tzLabel' => $this->dateTime->tzLabel,
        ];
    }

    // ─── Formatting ───────────────────────────────────────────────────

    public function format(string $pattern): string
    {
        $c = $this->components();
        $calendar = $this->calendar();
        // `dayOfYear`, `weekOfYear`, and `weekBasedYear` each trigger real
        // work — UAQ's dayOfYear is a bit-walk, and the week methods can
        // throw at calendar boundaries. The `str_contains` pre-check is a
        // zero-allocation `memchr`; if the token doesn't appear at all, the
        // escape-aware scan is never reached. `\W` / `\o` / `\z` patterns
        // hit `str_contains` as a false positive, then `patternContainsUnescaped`
        // rejects them — important because it also means an escaped token
        // at a calendar boundary never trips the throw.
        $dayOfYear     = str_contains($pattern, 'z') && self::patternContainsUnescaped($pattern, 'z') ? $this->dayOfYear() : 0;
        $weekOfYear    = str_contains($pattern, 'W') && self::patternContainsUnescaped($pattern, 'W') ? $this->weekOfYear() : 0;
        $weekBasedYear = str_contains($pattern, 'o') && self::patternContainsUnescaped($pattern, 'o') ? $this->weekBasedYear() : 0;

        // Lazily construct DateTimeImmutable only when timezone-dependent
        // tokens are present. The DTI is passed through FormatContext so the
        // formatter can delegate timezone math to PHP.
        $dti = self::patternContainsUnescaped($pattern, self::TZ_TOKENS)
            ? $this->dateTime->toDateTimeImmutable()
            : null;

        $ctx = new FormatContext(
            locale: $this->locale,
            calendarName: $calendar->localeFamily(),
            year: $c['year'],
            month: $c['month'],
            day: $c['day'],
            hour: $this->hour(),
            minute: $this->minute(),
            second: $this->second(),
            dayOfWeek: $this->dayOfWeek(),
            dayOfWeekIso: $this->dayOfWeekIso(),
            daysInMonth: $c['daysInMonth'],
            dayOfYear: $dayOfYear,
            weekOfYear: $weekOfYear,
            weekBasedYear: $weekBasedYear,
            isLeapYear: $c['isLeapYear'],
            tzLabel: $this->dateTime->tzLabel,
            digitScript: $this->digitScript,
            dateTimeImmutable: $dti,
        );
        return DateTokenFormatter::format($pattern, $ctx);
    }

    /** Format tokens whose output needs a DateTimeImmutable. */
    private const TZ_TOKENS = 'UOPpZIcrT';

    /**
     * Returns true if any character of `$tokens` appears unescaped in
     * `$pattern`. A backslash escapes the next character, so `\W` does not
     * count as a `W`. Runs in O(n) with no regex — roughly 20 ns per short
     * pattern.
     */
    private static function patternContainsUnescaped(string $pattern, string $tokens): bool
    {
        $len = strlen($pattern);
        for ($i = 0; $i < $len; $i++) {
            if ($pattern[$i] === '\\') {
                $i++;
                continue;
            }
            if (str_contains($tokens, $pattern[$i])) {
                return true;
            }
        }
        return false;
    }

    public function __toString(): string
    {
        return $this->format($this->defaultFormat());
    }

    public function withLocale(string $locale): static
    {
        return new static($this->dateTime, LocaleRegistry::get($locale), $this->digitScript);
    }

    public function withDigits(string $script): static
    {
        if (!DigitTransliterator::isSupported($script)) {
            throw new InvalidArgumentException("Unknown digit script '{$script}'.");
        }
        return new static($this->dateTime, $this->locale, $script);
    }

    // ─── Arithmetic ───────────────────────────────────────────────────

    public function addDays(int $days): CivilDateTime
    {
        return $this->dateTime->withJdn($this->dateTime->jdn + $days);
    }

    public function subDays(int $days): CivilDateTime
    {
        return $this->addDays(-$days);
    }

    public function addMonths(int $months): CivilDateTime
    {
        $c = $this->components();
        $calendar = $this->calendar();
        $year = $c['year'];
        $month = $c['month'];

        // Chunk by year so calendars whose year length varies across years
        // (Hebrew's 12-or-13-month year, future) stay correct. For v1 every
        // year has 12 months so this loop runs |months|/12 times.
        if ($months >= 0) {
            while (true) {
                $monthsInYear = $calendar->monthsInYear($year);
                if ($month + $months <= $monthsInYear) {
                    $month += $months;
                    break;
                }
                $months -= ($monthsInYear - $month + 1);
                $year++;
                $month = 1;
            }
        } else {
            $months = -$months;
            while (true) {
                if ($month - $months >= 1) {
                    $month -= $months;
                    break;
                }
                $months -= $month;
                $year--;
                $month = $calendar->monthsInYear($year);
            }
        }

        $dim = $calendar->daysInMonth($year, $month);
        $newDay = min($c['day'], $dim);
        return $this->dateTime->withJdn($calendar->toJdn($year, $month, $newDay));
    }

    public function subMonths(int $months): CivilDateTime
    {
        return $this->addMonths(-$months);
    }

    public function addYears(int $years): CivilDateTime
    {
        $c = $this->components();
        $newYear = $c['year'] + $years;
        $calendar = $this->calendar();
        $dim = $calendar->daysInMonth($newYear, $c['month']);
        $newDay = min($c['day'], $dim);
        return $this->dateTime->withJdn($calendar->toJdn($newYear, $c['month'], $newDay));
    }

    public function subYears(int $years): CivilDateTime
    {
        return $this->addYears(-$years);
    }

    public function startOfMonth(): CivilDateTime
    {
        $c = $this->components();
        return $this->dateTime->withJdn($this->calendar()->toJdn($c['year'], $c['month'], 1));
    }

    public function endOfMonth(): CivilDateTime
    {
        $c = $this->components();
        return $this->dateTime->withJdn(
            $this->calendar()->toJdn($c['year'], $c['month'], $c['daysInMonth']),
        );
    }

    /**
     * The same date-time with some parts replaced, in this view's calendar.
     * Parts left out (null) keep their current value; the result is
     * validated like a constructor call, so nothing is clamped.
     *
     *     $d->jalali()->with(day: 1);            // first of the month
     *     $d->jalali()->with(month: 7, day: 1);  // 1 Mehr, same year
     *     $d->gregorian()->with(hour: 9, minute: 0, second: 0);
     *
     * @throws InvalidDateException if the resulting date or time is invalid
     *         (e.g. `with(day: 31)` in a 30-day month)
     */
    public function with(
        ?int $year = null,
        ?int $month = null,
        ?int $day = null,
        ?int $hour = null,
        ?int $minute = null,
        ?int $second = null,
    ): CivilDateTime {
        $c = $this->components();
        $jdn = $this->calendar()->toJdn($year ?? $c['year'], $month ?? $c['month'], $day ?? $c['day']);

        return $this->dateTime
            ->withJdn($jdn)
            ->withTime($hour ?? $this->hour(), $minute ?? $this->minute(), $second ?? $this->second());
    }

    /** Quarter of the calendar year, 1–4 (months 1–3 are quarter 1). */
    public function quarter(): int
    {
        return intdiv($this->month() - 1, 3) + 1;
    }

    /** First day of this quarter, time of day kept (like `startOfMonth()`). */
    public function startOfQuarter(): CivilDateTime
    {
        $firstMonth = ($this->quarter() - 1) * 3 + 1;
        return $this->dateTime->withJdn($this->calendar()->toJdn($this->year(), $firstMonth, 1));
    }

    /** Last day of this quarter, time of day kept. */
    public function endOfQuarter(): CivilDateTime
    {
        $year = $this->year();
        $lastMonth = $this->quarter() * 3;
        $lastDay = $this->calendar()->daysInMonth($year, $lastMonth);
        return $this->dateTime->withJdn($this->calendar()->toJdn($year, $lastMonth, $lastDay));
    }

    public function startOfYear(): CivilDateTime
    {
        return $this->dateTime->withJdn($this->calendar()->toJdn($this->year(), 1, 1));
    }

    public function endOfYear(): CivilDateTime
    {
        $c = $this->components();
        $lastMonth = $this->calendar()->monthsInYear($c['year']);
        $lastDay = $this->calendar()->daysInMonth($c['year'], $lastMonth);
        return $this->dateTime->withJdn($this->calendar()->toJdn($c['year'], $lastMonth, $lastDay));
    }

    public function startOfWeek(WeekDay|int|null $weekStart = null): CivilDateTime
    {
        $weekStart ??= $this->locale->firstDayOfWeek();
        $weekStart = $weekStart instanceof WeekDay ? $weekStart->value : $weekStart;
        if ($weekStart < 1 || $weekStart > 7) {
            throw new InvalidArgumentException("weekStart must be in [1, 7]; got {$weekStart}.");
        }
        $isoDow = $this->dayOfWeekIso(); // Mon=1..Sun=7
        $offset = ($isoDow - $weekStart + 7) % 7;
        return $this->dateTime->withJdn($this->dateTime->jdn - $offset);
    }

    public function endOfWeek(WeekDay|int|null $weekStart = null): CivilDateTime
    {
        $startJdn = $this->startOfWeek($weekStart)->jdn;
        return $this->dateTime->withJdn($startJdn + 6);
    }

    public function weekDay(): WeekDay
    {
        return WeekDay::from($this->dayOfWeekIso());
    }

    public function isWeekend(): bool
    {
        return in_array($this->weekDay(), $this->locale->weekendDays(), true);
    }

    public function isWeekday(): bool
    {
        return !$this->isWeekend();
    }

    public function isInSupportedRange(): bool
    {
        [$min, $max] = $this->calendar()->supportedRange();
        return $this->dateTime->jdn >= $min && $this->dateTime->jdn <= $max;
    }

    public function diffInMonths(CivilDateTime $other): int
    {
        $calendar = $this->calendar();
        [$y1, $m1, $d1] = $calendar->fromJdn($this->dateTime->jdn);
        [$y2, $m2, $d2] = $calendar->fromJdn($other->jdn);

        $months = self::monthsBetween($calendar, $y2, $y1) + ($m1 - $m2);
        // Pull back one month if the day-of-month hasn't been reached yet in the
        // trailing direction, so that diffing (e.g.) 2026-03-15 ↔ 2026-04-14
        // returns 0, not 1.
        if ($months > 0 && $d1 < $d2) {
            $months--;
        } elseif ($months < 0 && $d1 > $d2) {
            $months++;
        }
        return $months;
    }

    public function diffInYears(CivilDateTime $other): int
    {
        $calendar = $this->calendar();
        [$y1, $m1, $d1] = $calendar->fromJdn($this->dateTime->jdn);
        [$y2, $m2, $d2] = $calendar->fromJdn($other->jdn);

        $years = $y1 - $y2;

        if ($years > 0) {
            if ($m1 < $m2 || ($m1 === $m2 && $d1 < $d2)) {
                $years--;
            }
        } elseif ($years < 0) {
            if ($m1 > $m2 || ($m1 === $m2 && $d1 > $d2)) {
                $years++;
            }
        }

        return $years;
    }

    // ─── Relative time ────────────────────────────────────────────────

    public function diffForHumans(CivilDateTime $other): string
    {
        $seconds = $this->dateTime->diffInSeconds($other);
        if ($seconds === 0) {
            return $this->locale->relativeTimeNow();
        }
        [$value, $unit] = $this->largestWholeUnit($other, abs($seconds));

        return DigitTransliterator::toScript(
            $this->locale->relativeTime($value, $unit, $seconds > 0),
            $this->digitScript,
        );
    }

    public function ago(): string
    {
        return $this->diffForHumans(CivilDateTime::now($this->dateTime->tzLabel));
    }

    /**
     * @return array{int, string} [count, unit]
     */
    private function largestWholeUnit(CivilDateTime $other, int $seconds): array
    {
        // No calendar's month is shorter than 28 days, so closer dates can
        // skip the (comparatively costly) calendar diffs.
        if (abs($this->dateTime->diffInDays($other)) >= 28) {
            $years = $this->wholeUnitsElapsed($other, $this->diffInYears($other), 'addYears');
            if ($years > 0) {
                return [$years, 'year'];
            }
            $months = $this->wholeUnitsElapsed($other, $this->diffInMonths($other), 'addMonths');
            if ($months > 0) {
                return [$months, 'month'];
            }
        }

        return match (true) {
            $seconds >= 86400 * 7 => [intdiv($seconds, 86400 * 7), 'week'],
            $seconds >= 86400     => [intdiv($seconds, 86400), 'day'],
            $seconds >= 3600      => [intdiv($seconds, 3600), 'hour'],
            $seconds >= 60        => [intdiv($seconds, 60), 'minute'],
            default               => [$seconds, 'second'],
        };
    }

    /**
     * `diffInMonths` / `diffInYears` compare dates only. For relative time
     * the time of day matters too: Feb 1 00:30 → Mar 1 00:00 is not yet a
     * whole month. Step `$other` forward by the date-based count and back
     * off by one if that overshoots this value.
     *
     * @param 'addMonths'|'addYears' $add
     * @return int absolute count
     */
    private function wholeUnitsElapsed(CivilDateTime $other, int $count, string $add): int
    {
        $direction = $count <=> 0;
        $anchor = static::of($other)->{$add}($count);
        // Overshooting means the anchor lies past this value in the
        // direction of travel.
        if (CivilDateTime::compare($anchor, $this->dateTime) === $direction) {
            $count -= $direction;
        }
        return abs($count);
    }

    // ─── Internals ────────────────────────────────────────────────────

    /**
     * Months from the start of `$fromYear` to the start of `$toYear`
     * (negative when `$toYear` is earlier). Walks `monthsInYear()` the same
     * way {@see addMonths()} does, so the two stay inverse for calendars
     * whose year length varies.
     */
    private static function monthsBetween(Calendar $calendar, int $fromYear, int $toYear): int
    {
        $total = 0;
        for ($y = min($fromYear, $toYear); $y < max($fromYear, $toYear); $y++) {
            $total += $calendar->monthsInYear($y);
        }
        return $toYear >= $fromYear ? $total : -$total;
    }

    /**
     * @return array{year:int, month:int, day:int, daysInMonth:int, isLeapYear:bool}
     */
    private function components(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }
        $calendar = $this->calendar();
        [$year, $month, $day] = $calendar->fromJdn($this->dateTime->jdn);
        return $this->cache = [
            'year' => $year,
            'month' => $month,
            'day' => $day,
            'daysInMonth' => $calendar->daysInMonth($year, $month),
            'isLeapYear' => $calendar->isLeapYear($year),
        ];
    }

}
