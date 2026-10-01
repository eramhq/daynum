# Formatting

Daynum uses PHP `date()` syntax. All tokens work across all calendars — the Jalali month `1` (`F`) renders as `Farvardin` / `فروردین`, the Gregorian month `1` renders as `January` / `ژانویهٔ`. Same token, calendar-aware output.

## Calling `format()`

`format()` lives on the calendar view, not the `CivilDateTime`. You choose the calendar, then ask for a string:

```php
$d = CivilDateTime::fromGregorian(2026, 4, 8, 14, 30, 0, 'Asia/Tehran');

$d->gregorian()->format('Y-m-d');     // "2026-04-08"
$d->jalali()->format('Y/m/d');        // "1405/01/19"
$d->hijri()->format('j F Y');         // "20 Shawwal 1447"
```

Default formats are `Y-m-d` for Gregorian and `Y/m/d` for Jalali/Hijri; `(string) $view` uses them.

## Token table

| Token | Output | Example (Jalali) |
|-------|--------|------------------|
| `Y` | Full year (4+ digits) | `1405` |
| `y` | 2-digit year | `05` |
| `m` | Month, zero-padded | `01` |
| `n` | Month, no padding | `1` |
| `d` | Day, zero-padded | `19` |
| `j` | Day, no padding | `19` |
| `F` | Month name, long (locale) | `Farvardin` / `فروردین` |
| `M` | Month name, short (locale) | `Far` / `فروردین` |
| `l` | Weekday name, long (locale) | `Wednesday` / `چهارشنبه` |
| `D` | Weekday name, short (locale) | `Wed` / `چهارشنبه` |
| `z` | Day of year, **0-indexed** | `18` |
| `W` | ISO week number, zero-padded | `16` |
| `o` | ISO week-based year | `1405` |
| `S` | Ordinal suffix (locale) | `th` |
| `G` | Hour 24h, no padding | `14` |
| `H` | Hour 24h, zero-padded | `14` |
| `g` | Hour 12h, no padding | `2` |
| `h` | Hour 12h, zero-padded | `02` |
| `i` | Minute, zero-padded | `30` |
| `s` | Second, zero-padded | `00` |
| `a` | am/pm (locale) | `pm` / `ب.ظ` |
| `A` | AM/PM (locale) | `PM` / `ب.ظ` |
| `N` | ISO weekday (Mon=1..Sun=7) | `3` |
| `w` | Weekday (Sun=0..Sat=6) | `3` |
| `t` | Days in month | `31` |
| `L` | Leap year (1/0) | `0` |
| `U` | Unix timestamp (requires tz) | `1775990400` |
| `P` | UTC offset `+HH:MM` (requires tz) | `+03:30` |
| `p` | UTC offset or `Z` for UTC (requires tz) | `Z` |
| `O` | UTC offset `+HHMM` (requires tz) | `+0330` |
| `Z` | UTC offset seconds (requires tz) | `12600` |
| `I` | DST flag `0`/`1` (requires tz) | `0` |
| `T` | Timezone abbreviation (requires tz) | `IRST` |
| `e` | Timezone identifier (requires tz) | `Asia/Tehran` |
| `c` | ISO 8601 composite (requires tz, **always Gregorian**) | `2026-04-08T14:30:00+03:30` |
| `r` | RFC 2822 date (requires tz, **always Gregorian**) | `Wed, 08 Apr 2026 14:30:00 +0330` |
| `u` | Microseconds (always `000000`) | `000000` |
| `v` | Milliseconds (always `000`) | `000` |

## Escaping

Backslash escapes the next character — `\Y` produces a literal `Y`:

```php
$d->gregorian()->format('\Y\e\a\r Y');    // "Year 2026"
$d->gregorian()->format('Y-m-d\T\Z');     // "2026-04-08TZ" (the Z here is literal)
```

## Composite `c` token

`c` is ISO 8601 and always renders in Gregorian regardless of the calling view — it's an interchange format, not a human-display format. `r` (RFC 2822) is the same way. Both require a timezone label on the `CivilDateTime`.

```php
$d = CivilDateTime::fromJalali(1405, 1, 19, 14, 30, 0, 'Asia/Tehran');
$d->jalali()->format('c');      // "2026-04-08T14:30:00+03:30" — Gregorian!
```

## Timezone-dependent tokens

`T`, `U`, `O`, `P`, `p`, `Z`, `I`, `c`, `r`, and `e` require a timezone label on the `CivilDateTime`. Calling them without one raises `MissingTimezoneException`:

```php
$d = CivilDateTime::fromGregorian(2026, 4, 8);       // no tzLabel
$d->gregorian()->format('Y-m-d H:i:s P');
// → MissingTimezoneException: Format token "P" requires a timezone ...
```

Attach one with `withTzLabel()` before formatting, or pass it at construction time.

## ISO week at calendar boundaries

`W` and `o` can throw `WeekAtBoundaryException` when the ISO week's Thursday falls outside the calendar's supported year range. This affects roughly the first or last 3 days of `MIN_YEAR` / `MAX_YEAR` for each calendar.

If you format dates near these extremes, catch the exception or avoid the `W` / `o` tokens:

```php
use Eram\Daynum\Exception\WeekAtBoundaryException;

try {
    $d->jalali()->format('o-\WW');
} catch (WeekAtBoundaryException) {
    $d->jalali()->format('Y-m-d');    // fall back
}
```

See [exceptions.md](exceptions.md#weekatboundaryexception).

## Digit script

Default output is ASCII digits. Switch with `withDigits()`:

```php
$d->jalali()->withDigits('persian')->format('Y/m/d');  // "۱۴۰۵/۰۱/۱۹"  (U+06F0..06F9)
$d->hijri()->withDigits('arab')->format('Y/m/d');      // "١٤٤٧/١٠/٢٠"  (U+0660..0669)
$d->gregorian()->withDigits('latn')->format('Y-m-d');  // "2026-04-08"
```

`persian` and `arab` are **distinct** Unicode scripts — Persian uses `U+06F0..06F9` and Arabic-Indic uses `U+0660..0669`. Treating them as interchangeable is a common bug. See [localization.md](localization.md).

Digit transliteration is applied *after* token substitution. Timezone-related output (`T U O P Z I c r e`) is protected from transliteration — offsets stay in ASCII even under a Persian locale, because they are programmatic output.

## Locale switching

```php
$d->jalali()->withLocale('fa')->format('l j F Y');
// "چهارشنبه 19 فروردین 1405"
```

`withLocale()` returns a new view (immutability). v1 ships `en`, `fa`, and `ar`; see [localization.md](localization.md).

## Relative time

```php
$now  = CivilDateTime::fromJalali(1405, 1, 19, 12, 0, 0, 'Asia/Tehran');
$past = $now->subDays(3);

$past->jalali()->diffForHumans($now);                                   // "3 days ago"
$now->addHours(2)->jalali()->diffForHumans($now);                       // "in 2 hours"
$past->jalali()->withLocale('fa')->withDigits('persian')->diffForHumans($now);  // "۳ روز پیش"
$past->hijri()->withLocale('ar')->withDigits('arab')->diffForHumans($now);      // "قبل ٣ أيام"

$comment->createdAt->jalali()->withLocale('fa')->ago();                 // against now()
```

`diffForHumans($other)` treats `$other` as "now"; `ago()` uses `CivilDateTime::now()` in the value's own timezone. Both pick the **largest whole unit**: year, month, week, day, hour, minute, second. The count is truncated, so 13 days is "1 week ago".

Years and months are counted in the **view's calendar**, with the same rule as [`diffInMonths`](arithmetic.md#diffinmonths--diffinyears--on-the-view): 29 days after 8 April 2026 is "in 4 weeks" in a Gregorian view but "in 1 month" in a Hijri view, because the Hijri month was only 29 days long. Unlike `diffInMonths`, a whole month or year also waits for the time of day: 1 January 23:59 to the next 1 January 00:00 is "11 months", not "1 year". Smaller units use wall-clock time (not DST-aware). On a `hijri()` view, both dates must be inside the Umm al-Qura table.

Two equal values give the locale's word for "now" (`now`, `اکنون`, `الآن`). Everything else matches `Intl.RelativeTimeFormat` with `numeric: 'always'` — always a number, so `"1 day ago"`, never `"yesterday"` — including Arabic dual and plural forms (`يومين`, `٣ أيام`, `١١ يومًا`). Digits follow `withDigits()` like every other output. Numbers of 1000 and above are not digit-grouped.

## See also

- [parsing.md](parsing.md) — which tokens can be parsed back (not all)
- [localization.md](localization.md) — locales, digit scripts, Arabic+Jalali limitation
- [exceptions.md](exceptions.md) — `WeekAtBoundaryException`, `MissingTimezoneException`
