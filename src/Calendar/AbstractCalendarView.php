<?php

declare(strict_types=1);

namespace Eram\Daynum\Calendar;

use Eram\Daynum\Calendar;
use Eram\Daynum\CalendarView;
use Eram\Daynum\Exception\DaynumException;
use Eram\Daynum\Exception\InvalidArgumentException;
use Eram\Daynum\Exception\ParseException;
use Eram\Daynum\Exception\WeekAtBoundaryException;
use Eram\Daynum\Formatter\DateTokenFormatter;
use Eram\Daynum\Formatter\DigitTransliterator;
use Eram\Daynum\Formatter\FormatContext;
use Eram\Daynum\Instant;
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
        protected readonly Instant $instant,
        protected readonly LocaleData $locale,
        protected readonly string $digitScript,
    ) {
    }

    abstract public function calendar(): Calendar;

    /**
     * Return the calendar singleton without constructing a view.
     * Used by {@see parseExact()} which is static and has no instance.
     */
    abstract protected static function calendarInstance(): Calendar;

    /** Default format pattern used by `__toString()`. */
    abstract protected function defaultFormat(): string;

    // ─── Instance factory (used by Instant) ───────────────────────────

    /**
     * @return static
     */
    public static function of(Instant $instant, ?string $locale = null, string $digitScript = DigitTransliterator::LATN): static
    {
        if (!DigitTransliterator::isSupported($digitScript)) {
            throw new InvalidArgumentException("Unknown digit script '{$digitScript}'.");
        }
        return new static($instant, LocaleRegistry::get($locale ?? 'en'), $digitScript);
    }

    // ─── Parsing ──────────────────────────────────────────────────────

    /**
     * Tokens that can be parsed: each maps to a field name.
     *
     * Fixed-width tokens (m, d, H, h, i, s) consume exactly 2 digits.
     * Variable-width tokens (n, j, G, g) consume 1-2 digits greedily.
     * Locale-dependent tokens (F, M, l, D) are excluded — format only.
     */
    private const PARSE_TOKENS = [
        'Y' => 'year',
        'm' => 'month',
        'n' => 'month',
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
     * Parse a date/time string in the given format, returning an Instant.
     *
     * Supported tokens:
     * `Y` (4+ digit year), `m`/`n` (month), `d`/`j` (day),
     * `H`/`G` (hour 24h), `h`/`g` (hour 12h), `i` (minute),
     * `s` (second), `a`/`A` (am/pm meridiem),
     * `P`/`p` (UTC offset `+HH:MM` or `Z`), `O` (UTC offset `+HHMM`),
     * `c` (ISO 8601 composite: `Y-m-d\TH:i:sP`).
     *
     * Variable-width tokens (`n`, `j`, `G`, `g`) consume 1-2 digits
     * greedily and must be followed by a literal separator, not another
     * token.
     *
     * Digits in any script (Persian U+06F0, Arabic-Indic U+0660) are
     * normalized to ASCII before parsing.
     *
     * @throws ParseException on format mismatch, unsupported tokens, or invalid date
     */
    public static function parseExact(string $text, string $format, ?string $tzLabel = null): Instant
    {
        // Normalize non-Latin digits to ASCII
        $text = DigitTransliterator::toLatin($text);

        $parts = self::tokenizeFormat($format);
        $pos = 0;
        $fields = [];

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
                $fields['year'] = $extracted['value'];
                $pos = $extracted['end'];
            } elseif ($token === 'a' || $token === 'A') {
                // Meridiem: 2-char (am/pm/AM/PM) or locale-specific
                $extracted = self::extractMeridiem($text, $pos);
                if ($extracted === null) {
                    throw ParseException::forFormat($text, $format, "expected am/pm at position {$pos}");
                }
                $fields['meridiem'] = $extracted['value'];
                $pos = $extracted['end'];
            } elseif ($token === 'n' || $token === 'j' || $token === 'G' || $token === 'g') {
                // Variable-width (1-2 digit) tokens
                $nextPart = $parts[$idx + 1] ?? null;
                $nextIsToken = $nextPart !== null && $nextPart['type'] === 'token';
                if ($nextIsToken) {
                    throw ParseException::forFormat($text, $format,
                        sprintf('variable-width token "%s" cannot be followed directly by another token without a literal separator', $token));
                }
                $extracted = self::extractVariableWidth($text, $pos);
                if ($extracted === null) {
                    throw ParseException::forFormat($text, $format,
                        sprintf('expected 1-2 digits for "%s" at position %d', $token, $pos));
                }
                $fields[$fieldName] = $extracted['value'];
                $pos = $extracted['end'];
            } elseif ($token === 'P' || $token === 'p') {
                $extracted = self::extractTzOffset($text, $pos, '/^([+-]\d{2}:\d{2})/', allowZ: true);
                if ($extracted === null) {
                    throw ParseException::forFormat($text, $format,
                        sprintf('expected timezone offset (+HH:MM or Z) for "%s" at position %d', $token, $pos));
                }
                $fields['tzOffsetP'] = $extracted['value'];
                $pos = $extracted['end'];
            } elseif ($token === 'O') {
                $extracted = self::extractTzOffset($text, $pos, '/^([+-]\d{4})/');
                if ($extracted === null) {
                    throw ParseException::forFormat($text, $format,
                        sprintf('expected timezone offset (+HHMM) for "O" at position %d', $pos));
                }
                $fields['tzOffsetO'] = $extracted['value'];
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
                $fields[$fieldName] = (int) $digits;
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
            if (!isset($fields['meridiem'])) {
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
            $fields['hour'] = self::resolve12Hour((int) $h12, (bool) $fields['meridiem']);
            unset($fields['hour12']);
        }
        unset($fields['meridiem']);

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

        $hour = (int) ($fields['hour'] ?? 0);
        $minute = (int) ($fields['minute'] ?? 0);
        $second = (int) ($fields['second'] ?? 0);

        if ($hour > 23 || $minute > 59 || $second > 59) {
            throw ParseException::forFormat($text, $format, sprintf(
                'time component out of range: hour=%d, minute=%d, second=%d',
                $hour, $minute, $second,
            ));
        }

        // Resolve parsed timezone offset — overrides the $tzLabel parameter
        $parsedTz = $tzLabel;
        if (isset($fields['tzOffsetP'])) {
            $parsedTz = (string) $fields['tzOffsetP'];
        } elseif (isset($fields['tzOffsetO'])) {
            $o = (string) $fields['tzOffsetO'];
            $parsedTz = substr($o, 0, 3) . ':' . substr($o, 3, 2);
        }

        // Validate via the calendar's toJdn (reuses all existing validation)
        $calendar = static::calendarInstance();

        try {
            $jdn = $calendar->toJdn((int) $year, (int) $month, (int) $day);
        } catch (DaynumException $e) {
            throw ParseException::forFormat($text, $format, $e->getMessage());
        }

        try {
            return new Instant($jdn, $hour * 3600 + $minute * 60 + $second, $parsedTz);
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
        'D' => true, 'l' => true, 'F' => true, 'M' => true,
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
    private static function extractYear(string $text, int $pos, bool $fixedWidth = false): ?array
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
     * Extract meridiem indicator (am/pm/AM/PM or locale variants like ق.ظ/ب.ظ).
     *
     * @return array{value: bool, end: int}|null  value = true for PM
     */
    private static function extractMeridiem(string $text, int $pos): ?array
    {
        $remaining = substr($text, $pos);

        // Standard am/pm (case-insensitive)
        if (preg_match('/^(am|pm)/i', $remaining, $m)) {
            return [
                'value' => strtolower($m[1]) === 'pm',
                'end' => $pos + strlen($m[1]),
            ];
        }

        // Persian meridiem: ق.ظ (AM) / ب.ظ (PM)
        $persianAm = 'ق.ظ';
        $persianPm = 'ب.ظ';
        if (str_starts_with($remaining, $persianPm)) {
            return ['value' => true, 'end' => $pos + strlen($persianPm)];
        }
        if (str_starts_with($remaining, $persianAm)) {
            return ['value' => false, 'end' => $pos + strlen($persianAm)];
        }

        // Arabic meridiem: ص (AM) / م (PM)
        if (str_starts_with($remaining, 'م')) {
            return ['value' => true, 'end' => $pos + strlen('م')];
        }
        if (str_starts_with($remaining, 'ص')) {
            return ['value' => false, 'end' => $pos + strlen('ص')];
        }

        return null;
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
            $raw = $m[1];
            // Real-world IANA range: -12:00 (Baker Island) to +14:00 (Kiribati)
            $sign = $raw[0];
            $h = (int) substr($raw, 1, 2);
            $min = (int) substr($raw, strlen($raw) === 6 && $raw[3] === ':' ? 4 : 3, 2);
            $maxH = $sign === '-' ? 12 : 14;
            if ($h > $maxH || $min > 59 || ($h === $maxH && $min > 0)) {
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
     * Try to parse the given text; return null instead of throwing.
     *
     * Catches {@see ParseException} only — this covers all failure modes
     * because {@see parseExact()} already wraps calendar-level exceptions
     * (InvalidDateException, UmmAlQuraOutOfRangeException) in ParseException.
     */
    public static function tryParseExact(string $text, string $format, ?string $tzLabel = null): ?Instant
    {
        try {
            return static::parseExact($text, $format, $tzLabel);
        } catch (ParseException) {
            return null;
        }
    }

    // ─── CalendarView interface ───────────────────────────────────────

    public function instant(): Instant
    {
        return $this->instant;
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
        return intdiv($this->instant->secondsOfDay, 3600);
    }

    public function minute(): int
    {
        return intdiv($this->instant->secondsOfDay % 3600, 60);
    }

    public function second(): int
    {
        return $this->instant->secondsOfDay % 60;
    }

    public function dayOfWeek(): int
    {
        // JDN 0 was a Monday. PHP's w is Sun=0..Sat=6.
        // JDN 0 (Mon) → w=1. Formula: ((jdn + 1) mod 7), normalized.
        $w = ($this->instant->jdn + 1) % 7;
        if ($w < 0) {
            $w += 7;
        }
        return $w;
    }

    public function dayOfWeekIso(): int
    {
        // Monday = 1, Sunday = 7.
        $iso = $this->instant->jdn % 7;
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
        // ISO 8601 week number. A week belongs to the year of its Thursday.
        // Algorithm: offset the JDN to the Thursday of its week, then count
        // weeks since the Thursday of week 1 of that year.
        $jdn = $this->instant->jdn;
        $isoDow = $this->dayOfWeekIso();           // Mon=1..Sun=7
        $thursdayJdn = $jdn - $isoDow + 4;          // JDN of this week's Thursday
        $calendar = $this->calendar();

        try {
            [$thursdayYear, , ] = $calendar->fromJdn($thursdayJdn);
            // JDN of Jan 4 of $thursdayYear — always in ISO week 1.
            $jan4 = $calendar->toJdn($thursdayYear, 1, 4);
        } catch (DaynumException $e) {
            // A sentinel `1` would collide with the real week 1 at the MIN
            // edge and be indistinguishable from the current year's week 1
            // at the MAX edge — throwing is the only non-misleading outcome.
            throw WeekAtBoundaryException::forJdn($thursdayJdn, $e);
        }

        $jan4Iso = (($jan4 % 7) + 7) % 7 + 1;
        $firstThursday = $jan4 - $jan4Iso + 4;

        return intdiv($thursdayJdn - $firstThursday, 7) + 1;
    }

    public function weekBasedYear(): int
    {
        // ISO 8601 week-based year — the year owning the ISO week of this
        // date's Thursday. Differs from `year()` by ±1 around Jan 1 / Dec 31.
        $jdn = $this->instant->jdn;
        $isoDow = $this->dayOfWeekIso();
        $thursdayJdn = $jdn - $isoDow + 4;
        $calendar = $this->calendar();

        try {
            [$thursdayYear, , ] = $calendar->fromJdn($thursdayJdn);
        } catch (DaynumException $e) {
            throw WeekAtBoundaryException::forJdn($thursdayJdn, $e);
        }

        // Fast path: when the Thursday falls in the view's own year, the
        // year is known-valid by construction and no probe is needed. This
        // covers roughly 361/365 days — only early-Jan / late-Dec dates
        // need to validate a notionally-adjacent year.
        if ($thursdayYear === $this->components()['year']) {
            return $thursdayYear;
        }

        // Closed-form `fromJdn` implementations (HijriCivil, Jalali,
        // Gregorian) can return a notional out-of-range year that only
        // `toJdn` would reject. Validate by attempting Jan 4 — any year
        // ISO week 1 references must itself be a valid year of this calendar.
        try {
            $calendar->toJdn($thursdayYear, 1, 4);
        } catch (DaynumException $e) {
            throw WeekAtBoundaryException::forYear($thursdayYear, $e);
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
            'tzLabel' => $this->instant->tzLabel,
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
        $dayOfYear     = str_contains($pattern, 'z') && self::patternContainsUnescaped($pattern, 'z') ? $this->dayOfYear()     : 0;
        $weekOfYear    = str_contains($pattern, 'W') && self::patternContainsUnescaped($pattern, 'W') ? $this->weekOfYear()    : 0;
        $weekBasedYear = str_contains($pattern, 'o') && self::patternContainsUnescaped($pattern, 'o') ? $this->weekBasedYear() : 0;

        // Lazily construct DateTimeImmutable only when timezone-dependent
        // tokens are present. The DTI is passed through FormatContext so the
        // formatter can delegate timezone math to PHP.
        $tzTokens = ['U','O','P','p','Z','I','c','r','T'];
        $dti = null;
        foreach ($tzTokens as $t) {
            if (str_contains($pattern, $t) && self::patternContainsUnescaped($pattern, $t)) {
                $dti = $this->instant->toDateTimeImmutable();
                break;
            }
        }

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
            tzLabel: $this->instant->tzLabel,
            digitScript: $this->digitScript,
            dateTimeImmutable: $dti,
        );
        return DateTokenFormatter::format($pattern, $ctx);
    }

    /**
     * Returns true if `$char` appears unescaped in `$pattern`. A backslash
     * escapes the next character, so `\W` does not count as a `W`. Runs in
     * O(n) with no regex — roughly 20 ns per short pattern.
     */
    private static function patternContainsUnescaped(string $pattern, string $char): bool
    {
        $len = strlen($pattern);
        for ($i = 0; $i < $len; $i++) {
            if ($pattern[$i] === '\\') {
                $i++;
                continue;
            }
            if ($pattern[$i] === $char) {
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
        return new static($this->instant, LocaleRegistry::get($locale), $this->digitScript);
    }

    public function withDigits(string $script): static
    {
        if (!DigitTransliterator::isSupported($script)) {
            throw new InvalidArgumentException("Unknown digit script '{$script}'.");
        }
        return new static($this->instant, $this->locale, $script);
    }

    // ─── Arithmetic ───────────────────────────────────────────────────

    public function addDays(int $days): Instant
    {
        return $this->instant->withJdn($this->instant->jdn + $days);
    }

    public function subDays(int $days): Instant
    {
        return $this->addDays(-$days);
    }

    public function addMonths(int $months): Instant
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
        return $this->instant->withJdn($calendar->toJdn($year, $month, $newDay));
    }

    public function subMonths(int $months): Instant
    {
        return $this->addMonths(-$months);
    }

    public function addYears(int $years): Instant
    {
        $c = $this->components();
        $newYear = $c['year'] + $years;
        $calendar = $this->calendar();
        $dim = $calendar->daysInMonth($newYear, $c['month']);
        $newDay = min($c['day'], $dim);
        return $this->instant->withJdn($calendar->toJdn($newYear, $c['month'], $newDay));
    }

    public function subYears(int $years): Instant
    {
        return $this->addYears(-$years);
    }

    public function startOfMonth(): Instant
    {
        $c = $this->components();
        return $this->instant->withJdn($this->calendar()->toJdn($c['year'], $c['month'], 1));
    }

    public function endOfMonth(): Instant
    {
        $c = $this->components();
        return $this->instant->withJdn(
            $this->calendar()->toJdn($c['year'], $c['month'], $c['daysInMonth'])
        );
    }

    public function startOfYear(): Instant
    {
        return $this->instant->withJdn($this->calendar()->toJdn($this->year(), 1, 1));
    }

    public function endOfYear(): Instant
    {
        $c = $this->components();
        $lastMonth = $this->calendar()->monthsInYear($c['year']);
        $lastDay = $this->calendar()->daysInMonth($c['year'], $lastMonth);
        return $this->instant->withJdn($this->calendar()->toJdn($c['year'], $lastMonth, $lastDay));
    }

    public function startOfWeek(WeekDay|int $weekStart = WeekDay::Monday): Instant
    {
        $weekStart = $weekStart instanceof WeekDay ? $weekStart->value : $weekStart;
        if ($weekStart < 1 || $weekStart > 7) {
            throw new InvalidArgumentException("weekStart must be in [1, 7]; got {$weekStart}.");
        }
        $isoDow = $this->dayOfWeekIso(); // Mon=1..Sun=7
        $offset = ($isoDow - $weekStart + 7) % 7;
        return $this->instant->withJdn($this->instant->jdn - $offset);
    }

    public function endOfWeek(WeekDay|int $weekStart = WeekDay::Monday): Instant
    {
        $startJdn = $this->startOfWeek($weekStart)->jdn;
        return $this->instant->withJdn($startJdn + 6);
    }

    public function isInSupportedRange(): bool
    {
        [$min, $max] = $this->calendar()->supportedRange();
        return $this->instant->jdn >= $min && $this->instant->jdn <= $max;
    }

    public function diffInMonths(Instant $other): int
    {
        $calendar = $this->calendar();
        [$y1, $m1, $d1] = $calendar->fromJdn($this->instant->jdn);
        [$y2, $m2, $d2] = $calendar->fromJdn($other->jdn);

        $months = ($y1 - $y2) * 12 + ($m1 - $m2);
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

    public function diffInYears(Instant $other): int
    {
        $calendar = $this->calendar();
        [$y1, $m1, $d1] = $calendar->fromJdn($this->instant->jdn);
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

    // ─── Internals ────────────────────────────────────────────────────

    /**
     * @return array{year:int, month:int, day:int, daysInMonth:int, isLeapYear:bool}
     */
    private function components(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }
        $calendar = $this->calendar();
        [$year, $month, $day] = $calendar->fromJdn($this->instant->jdn);
        return $this->cache = [
            'year' => $year,
            'month' => $month,
            'day' => $day,
            'daysInMonth' => $calendar->daysInMonth($year, $month),
            'isLeapYear' => $calendar->isLeapYear($year),
        ];
    }

}
