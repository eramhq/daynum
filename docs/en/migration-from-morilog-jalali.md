# Migrating from `morilog/jalali`

Daynum covers what most projects use `morilog/jalali` for — Jalali ↔ Gregorian conversion, Jalali formatting and arithmetic — plus Hijri, with zero runtime dependencies. It is **not** a drop-in replacement: the API is shaped differently, and every call site needs a small rewrite. This page lists the rewrites.

## TL;DR

1. `composer require eram/daynum:^1.0@beta` (both packages can be installed side by side while you migrate).
2. Rewrite call sites using the table below, one file at a time.
3. `composer remove morilog/jalali` once nothing references `Morilog\Jalali\` or `jdate()`.

## There is no `jdate()` in Daynum

Daynum ships no global functions. That is deliberate: the name `jdate()` already means two incompatible things in the PHP world —

- `morilog/jalali`: `jdate($str = null)` → a `Jalalian` object.
- jdf.php (and its many copies): `jdate($format, $timestamp = '', …)` → a formatted string.

A third `jdate()` with yet another signature would either clash or be silently skipped by a `function_exists` guard, and every existing call would break at runtime rather than at install time. Rewrite `jdate()` calls explicitly (see the table). If you want a short helper, define one in your own namespace so its signature is yours:

```php
namespace App\Support;

use Eram\Daynum\Calendar\Jalali\JalaliView;
use Eram\Daynum\CivilDateTime;

function jalali(CivilDateTime $d): JalaliView
{
    return $d->jalali()->withLocale('fa')->withDigits('persian');
}
```

## Before / after

| `morilog/jalali` | Daynum |
|---|---|
| `jdate()` / `Jalalian::now()` | `CivilDateTime::now('Asia/Tehran')->jalali()` |
| `jdate($dateTime)` / `Jalalian::fromDateTime($dt)` | `CivilDateTime::fromDateTime($dt)->jalali()` |
| `Jalalian::forge($timestamp)` / `jdate($timestamp)` | `CivilDateTime::fromTimestamp($timestamp, 'Asia/Tehran')->jalali()` |
| `Jalalian::fromCarbon($carbon)` | `CivilDateTime::fromDateTime($carbon)->jalali()` (Carbon is a `DateTimeInterface`) |
| `new Jalalian(1405, 1, 19)` | `CivilDateTime::fromJalali(1405, 1, 19)` |
| `Jalalian::fromFormat('Y/m/d', $s)` | `JalaliView::parseExact($s, 'Y/m/d')` |
| `CalendarUtils::toJalali(2026, 4, 8)` | `CivilDateTime::fromGregorian(2026, 4, 8)->jalali()->toArray()` |
| `CalendarUtils::toGregorian(1405, 1, 19)` | `CivilDateTime::fromJalali(1405, 1, 19)->gregorian()->toArray()` |
| `CalendarUtils::checkDate($y, $m, $d)` | `CivilDateTime::isValidJalali($y, $m, $d)` |
| `CalendarUtils::convertNumbers($s)` | `DigitTransliterator::toScript($s, 'persian')` |
| `->format('%A، %d %B %Y')` (strftime `%` tokens) | `->format('l، d F Y')` (PHP `date()` tokens, see below) |
| `->getYear()` / `getMonth()` / `getDay()` | `->year()` / `month()` / `day()` |
| `->getHour()` / `getMinute()` / `getSecond()` | `->hour()` / `minute()` / `second()` |
| `->getMonthDays()` | `->daysInMonth()` |
| `->isLeapYear()` | `->isLeapYear()` |
| `->getDayOfWeek()` (Saturday = 0) | `->dayOfWeek()` (Sunday = 0, PHP `date('w')`) or `->dayOfWeekIso()` |
| `->addDays(3)` / `addMonths` / `addYears` | `->addDays(3)` … — returns a `CivilDateTime`, see below |
| `->addHours(2)` / `addMinutes` / `addSeconds` | `$d->addHours(2)` (on the `CivilDateTime`, wall-clock) |
| `->getTimestamp()` | `$d->toTimestamp()` |
| `->toCarbon()` | `Carbon::instance($d->toDateTimeImmutable())` |

### strftime tokens → `date()` tokens

`morilog/jalali`'s `format()` takes strftime-style `%` tokens. Daynum uses PHP `date()` tokens everywhere ([formatting.md](formatting.md)):

| strftime | `date()` | Meaning |
|---|---|---|
| `%Y` | `Y` | 4-digit year |
| `%y` | `y` | 2-digit year |
| `%m` / `%n` | `m` / `n` | month, padded / unpadded |
| `%d` / `%e` | `d` / `j` | day, padded / unpadded |
| `%B` / `%b` | `F` / `M` | month name, full / short |
| `%A` / `%a` | `l` / `D` | weekday name, full / short |
| `%H` / `%I` | `H` / `h` | hour, 24h / 12h |
| `%M` / `%S` | `i` / `s` | minute / second |
| `%p` | `A` | AM/PM |

## Key differences

### 1. Values are `CivilDateTime`; calendars are views

`Jalalian` mixes "which date" and "which calendar" into one object. Daynum splits them: `CivilDateTime` is the date-time value, and `jalali()`, `gregorian()`, `hijri()` are views onto it.

```php
// morilog/jalali
$d = Jalalian::fromFormat('Y/m/d', '1405/01/19');
echo $d->format('%A %d %B %Y');

// Daynum
$d = JalaliView::parseExact('1405/01/19', 'Y/m/d');          // CivilDateTime
echo $d->jalali()->withLocale('fa')->format('l d F Y');      // enter a view to format
```

The same `CivilDateTime` is also `$d->gregorian()` and `$d->hijri()` with no conversion code.

### 2. Arithmetic returns `CivilDateTime`, not a view

```php
// morilog/jalali
echo $d->addMonths(1)->format('Y/m/d');

// Daynum
echo $d->jalali()->addMonths(1)->jalali()->format('Y/m/d');
```

Arithmetic is calendar-specific ("one Jalali month"), but its result is calendar-neutral. See [concepts.md](concepts.md#civildatetime-vs-view).

### 3. Locale and digits are per view, not global

`morilog/jalali` renders Persian names by default and converts digits through `CalendarUtils::convertNumbers()`. Daynum defaults to English names and Latin digits; choose per view:

```php
$d->jalali()->withLocale('fa')->withDigits('persian')->format('Y/m/d');
// "۱۴۰۵/۰۱/۱۹"
```

No global state, so nothing leaks between requests.

### 4. Parsing is strict

`parseExact` matches the format character for character. For free-form user input, chain `tryParseExact` and fall back to `DateTimeImmutable`:

```php
$d = JalaliView::tryParseExact($raw, 'Y/m/d')
   ?? JalaliView::tryParseExact($raw, 'Y-m-d')
   ?? CivilDateTime::fromDateTime(new DateTimeImmutable($raw));
```

See [parsing.md](parsing.md).

### 5. Time zones are labels; comparison is wall-clock

A `CivilDateTime` stores a time-zone label next to the wall-clock date and time; it does not convert anything. Two values with the same wall-clock reading compare equal even if their labels differ. For real time-zone math, use `toDateTimeImmutable()`.

See [timezones.md](timezones.md) and [concepts.md](concepts.md#civildatetime-is-wall-clock-time).

### 6. No relative-date parsing

`Jalalian` accepts Carbon-style strings like `"next Monday"`. Daynum does not parse them; let PHP do it first:

```php
$d = CivilDateTime::fromDateTime(new DateTimeImmutable('next Saturday', new DateTimeZone('Asia/Tehran')));
```

## Arabic month names on Jalali throw

Formatting Jalali with `withLocale('ar')` and an `F`/`M` token throws. Use `withLocale('fa')` — Persian month names are in the same script. See [localization.md](localization.md#the-arabic--jalali-limitation).

## Feature comparison

| Feature | `morilog/jalali` | Daynum | Notes |
|---|---|---|---|
| Jalali ↔ Gregorian conversion | ✓ | ✓ | Same Birashk 33-year cycle (via jalaali-js) |
| Hijri calendar | ✗ | ✓ | Umm al-Qura + civil |
| Format tokens | strftime `%` | PHP `date()` |  |
| Persian digits | `convertNumbers()` | per view |  |
| Relative parsing ("tomorrow") | ✓ | ✗ | Use `DateTimeImmutable` |
| Global `jdate()` helper | ✓ | ✗ | Write your own, see above |
| Immutable | mostly | always |  |
| ICU-tested | ✗ | ✓ |  |
| Runtime dependencies | Carbon | none |  |

## See also

- [calendars/jalali.md](calendars/jalali.md) — algorithm details, ICU divergence windows
- [getting-started.md](getting-started.md)
- [cookbook.md](cookbook.md) — Laravel integration patterns
- [faq.md](faq.md)
