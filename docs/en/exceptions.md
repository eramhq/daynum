# Exceptions

Daynum defines 7 concrete exception types plus a marker interface. Every exception the library throws implements `Daynum\Exception\DaynumException`, so one `catch` catches the whole library:

```php
use Daynum\Exception\DaynumException;

try {
    // ... any Daynum code
} catch (DaynumException $e) {
    // exclusively Daynum — not random user-code throwables
}
```

## Hierarchy

```text
Daynum\Exception\DaynumException (marker interface extends \Throwable)
│
├─ InvalidArgumentException      (extends \InvalidArgumentException)
├─ InvalidDateException          (extends \InvalidArgumentException)
├─ ParseException                (extends \InvalidArgumentException)
│
├─ InvalidTimezoneException      (extends \RuntimeException)
├─ MissingTimezoneException      (extends \RuntimeException)
├─ WeekAtBoundaryException       (extends \RuntimeException)
│
└─ UmmAlQuraOutOfRangeException  (extends \OutOfRangeException)
```

All exceptions are `final`. They extend appropriate SPL base classes so existing generic catch blocks (`catch (\InvalidArgumentException $e)`, `catch (\OutOfRangeException $e)`) still work.

## `InvalidDateException`

**Extends**: `\InvalidArgumentException`

**Thrown by**: `Instant::fromGregorian`, `fromJalali`, `fromHijri`, `fromHijriCivil` (via the calendar's `toJdn`), the `Instant` constructor for out-of-range time-of-day, calendar `daysInMonth` for invalid month numbers.

**When**: the components do not form a valid date in the target calendar. Examples:

- February 30, 31
- Month 0 or 13
- Day 0 or >31
- Time-of-day hour > 23, minute > 59, second > 59
- Year outside a calendar's supported range (Gregorian `-9999..9999`, Jalali `1..3177`, HijriCivil `1..9666`)

**How to recover**:

```php
use Daynum\Exception\InvalidDateException;

try {
    $d = Instant::fromJalali(1405, 13, 1);
} catch (InvalidDateException $e) {
    // $e->getMessage() → "Invalid jalali date 1405-13-01: month must be in [1, 12]"
}

// Or preflight
if (Instant::isValidJalali($year, $month, $day)) {
    $d = Instant::fromJalali($year, $month, $day);
}

// Or use the safe variant
$d = Instant::tryFromJalali($year, $month, $day);   // null on invalid
```

## `InvalidArgumentException`

**Extends**: `\InvalidArgumentException`

**Thrown by**: `Instant::fromArray()` on malformed input; `withLocale()` on unknown locale tags; `withDigits()` on unknown digit scripts; `startOfWeek()`/`endOfWeek()` on out-of-range `weekStart`.

**When**: a method received an argument outside its accepted domain that isn't date-component-invalid.

```php
$d->jalali()->withLocale('zh');       // throws: "Unknown locale 'zh'. Daynum ships 'en', 'fa', 'ar'."
$d->jalali()->withDigits('xyz');      // throws: "Unknown digit script 'xyz'."
Instant::fromArray(['jdn' => '2461139']);  // throws: "fromArray() requires an integer 'jdn' key"
```

## `ParseException`

**Extends**: `\InvalidArgumentException`

**Thrown by**: `CalendarView::parseExact()`.

**When**:

- Format / input mismatch (literal doesn't match, wrong number of digits)
- Unsupported or format-only token used in the format string
- Variable-width token (`n`, `j`, `G`, `g`) followed by another token without a literal separator
- 12-hour (`h`/`g`) token without a companion meridiem token
- Trailing input after the last parsed token
- Time component out of range (`hour > 23`, etc.)
- The underlying calendar rejects the parsed components (wraps `InvalidDateException` and `UmmAlQuraOutOfRangeException`)

**How to recover**:

```php
use Daynum\Exception\ParseException;

try {
    $d = JalaliView::parseExact($input, 'Y/m/d');
} catch (ParseException $e) {
    // report the error or accept $input as free text
}

// Or use the safe variant
$d = JalaliView::tryParseExact($input, 'Y/m/d');   // null on any parse failure
```

`tryParseExact` catches `ParseException` specifically — and since `parseExact` already wraps underlying calendar errors in `ParseException`, it catches them all uniformly.

## `InvalidTimezoneException`

**Extends**: `\RuntimeException`

**Thrown by**: the `Instant` constructor (via `resolveZone`) when it can't build a `DateTimeZone` from the given `tzLabel`.

**When**: `tzLabel` is non-null and PHP's `new DateTimeZone($label)` throws — typically because the label is a typo, a legacy abbreviation, or a deprecated identifier.

```php
Instant::fromGregorian(2026, 4, 8, 0, 0, 0, 'Tehran');
// → InvalidTimezoneException: Invalid or unknown timezone: "Tehran".
// (correct: 'Asia/Tehran')
```

**How to recover**: validate upstream with PHP's `DateTimeZone::listIdentifiers()` before constructing, or catch and fall back to a default.

## `MissingTimezoneException`

**Extends**: `\RuntimeException`

**Thrown by**: `DateTokenFormatter` when a timezone-dependent token is used on an `Instant` without a `tzLabel`.

**When**: you call `format()` with any of `T`, `U`, `O`, `P`, `p`, `Z`, `I`, `c`, `r`, or `e` and the `Instant`'s `tzLabel` is `null`.

```php
$d = Instant::fromGregorian(2026, 4, 8);    // no tzLabel
$d->gregorian()->format('Y-m-d H:i P');
// → MissingTimezoneException: Format token "P" requires a timezone ...
```

**How to recover**: attach a timezone before formatting:

```php
$d = $d->withTzLabel('Asia/Tehran');
$d->gregorian()->format('Y-m-d H:i P');
```

Or drop the tz-dependent token from the pattern. See [timezones.md](timezones.md).

## `UmmAlQuraOutOfRangeException`

**Extends**: `\OutOfRangeException`

**Thrown by**: `HijriUmmAlQuraCalendar::toJdn`, `fromJdn`, `isLeapYear`, `daysInMonth`, `dayOfYear` — i.e., `Instant::fromHijri` at construction and `$d->hijri()->year()` / `->month()` / `->format()` at read time.

**When**: the date (or JDN) falls outside the bundled UAQ table range (`Table::MIN_YEAR` to `Table::MAX_YEAR`, currently AH 1300–1600). Outside that window, ICU's own UAQ data silently falls through to the arithmetic civil calendar, which Daynum refuses to mirror.

**How to recover**: fall back to `hijriCivil()`, which has no range limit:

```php
use Daynum\Exception\UmmAlQuraOutOfRangeException;

try {
    $d = Instant::fromHijri($year, $month, $day);
    $formatted = $d->hijri()->format('j F Y');
} catch (UmmAlQuraOutOfRangeException) {
    $d = Instant::fromHijriCivil($year, $month, $day);
    $formatted = $d->hijriCivil()->format('j F Y');
}

// Or preflight
if (Instant::isValidHijri($year, $month, $day)) {
    $view = $instant->hijri();
} else {
    $view = $instant->hijriCivil();
}
```

See [calendars/hijri-umm-al-qura.md](calendars/hijri-umm-al-qura.md) and [arithmetic.md](arithmetic.md#uaq-boundary-crossing-via-arithmetic).

## `WeekAtBoundaryException`

**Extends**: `\RuntimeException`

**Thrown by**: `CalendarView::weekOfYear()`, `weekBasedYear()`, and the `W` / `o` format tokens.

**When**: the ISO 8601 week of this date's Thursday falls outside the calendar's supported year range. This affects roughly the first or last 3 days of `MIN_YEAR` / `MAX_YEAR` for each calendar. For example, `AH 1 Muharram 1` (HijriCivil) is a Friday whose containing week's Thursday is the day *before* the calendar's epoch.

Rather than returning a misleading sentinel that would silently collide with real week 1, Daynum throws.

**How to recover**: catch near the boundary, or avoid `W` / `o` when you know the input might touch `MIN_YEAR` / `MAX_YEAR`:

```php
use Daynum\Exception\WeekAtBoundaryException;

try {
    $weekLabel = $d->gregorian()->format('o-\WW');
} catch (WeekAtBoundaryException) {
    $weekLabel = $d->gregorian()->format('Y-m-d');   // fall back to date
}
```

## Catch-all

```php
use Daynum\Exception\DaynumException;

try {
    $d = Instant::fromHijri($year, $month, $day);
    echo $d->hijri()->format($pattern);
} catch (DaynumException $e) {
    // Covers every exception Daynum defines — including future ones —
    // without swallowing unrelated errors from user code.
    logger()->warning('Daynum error: ' . $e->getMessage());
}
```

## See also

- [parsing.md](parsing.md#error-modes)
- [timezones.md](timezones.md#missingtimezoneexception)
- [calendars/hijri-umm-al-qura.md](calendars/hijri-umm-al-qura.md)
- [formatting.md](formatting.md#iso-week-at-calendar-boundaries)
