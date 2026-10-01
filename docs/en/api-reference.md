# API Reference

Hand-written, grouped by type. For narrative docs see [getting-started.md](getting-started.md), [concepts.md](concepts.md), and the topic pages.

**Jump to:** [`CivilDateTime`](#eramdaynumcivildatetime) · [`CalendarView`](#eramdaynumcalendarview) · [`Calendar`](#eramdaynumcalendar) · [`WeekDay`](#eramdaynumweekday-enum) · [Exceptions](#exceptions) · [`LocaleRegistry`](#eramdaynumlocalelocaleregistry) · [`DigitTransliterator`](#eramdaynumformatterdigittransliterator)

## `Eram\Daynum\CivilDateTime`

The immutable calendar-agnostic core value. Triple of `(jdn, secondsOfDay, tzLabel)`. See [concepts.md](concepts.md).

### Properties

| | | |
|---|---|---|
| `jdn` | `int` | Julian Day Number |
| `secondsOfDay` | `int` | `[0, 86400)` |
| `tzLabel` | `?string` | IANA name, fixed offset, or `null` |

### Constructor

```php
new CivilDateTime(int $jdn, int $secondsOfDay = 0, ?string $tzLabel = null)
```

Throws `InvalidDateException` if `secondsOfDay` is out of range.

### Construction from calendar components

```php
CivilDateTime::fromGregorian(int $y, int $m, int $d, int $h=0, int $min=0, int $s=0, ?string $tz=null): CivilDateTime
CivilDateTime::fromJalali   (int $y, int $m, int $d, int $h=0, int $min=0, int $s=0, ?string $tz=null): CivilDateTime
CivilDateTime::fromHijri    (int $y, int $m, int $d, int $h=0, int $min=0, int $s=0, ?string $tz=null): CivilDateTime
CivilDateTime::fromHijriCivil(int $y, int $m, int $d, int $h=0, int $min=0, int $s=0, ?string $tz=null): CivilDateTime
```

`fromHijri` is Saudi Umm al-Qura; `fromHijriCivil` is the tabular `islamic-civil` variant. See [calendars/hijri-umm-al-qura.md](calendars/hijri-umm-al-qura.md) and [calendars/hijri-civil.md](calendars/hijri-civil.md).

### Safe construction

```php
CivilDateTime::tryFromGregorian(...): ?CivilDateTime
CivilDateTime::tryFromJalali(...):    ?CivilDateTime
CivilDateTime::tryFromHijri(...):     ?CivilDateTime
CivilDateTime::tryFromHijriCivil(...): ?CivilDateTime

CivilDateTime::isValidGregorian(int $y, int $m, int $d): bool
CivilDateTime::isValidJalali(int $y, int $m, int $d):    bool
CivilDateTime::isValidHijri(int $y, int $m, int $d):     bool
CivilDateTime::isValidHijriCivil(int $y, int $m, int $d): bool
```

`tryFrom*` return `null` on invalid input instead of throwing. `isValid*` is equivalent to `tryFrom*(...) !== null`.

### Interop

```php
CivilDateTime::fromDateTime(DateTimeInterface $dt): CivilDateTime
CivilDateTime::fromTimestamp(int $timestamp, string $tzLabel = 'UTC'): CivilDateTime
$dateTime->toDateTimeImmutable(): DateTimeImmutable
$dateTime->toTimestamp(): int
```

`fromDateTime` reads the proleptic Gregorian date, time-of-day, and timezone name. `fromTimestamp` is the wall-clock reading of a Unix timestamp in the given zone. `toTimestamp` throws `MissingTimezoneException` when `tzLabel` is `null`. `toDateTimeImmutable` is the escape hatch for real timezone math. See [timezones.md](timezones.md).

### Current-date helpers

```php
CivilDateTime::now(?string $tzLabel = null):       CivilDateTime   // current date + time
CivilDateTime::today(?string $tzLabel = null):     CivilDateTime   // today at 00:00:00
CivilDateTime::tomorrow(?string $tzLabel = null):  CivilDateTime   // tomorrow at 00:00:00
CivilDateTime::yesterday(?string $tzLabel = null): CivilDateTime   // yesterday at 00:00:00
```

When `$tzLabel` is `null`, resolves from `date_default_timezone_get()` and stores it on the result.

### Calendar views

```php
$dateTime->gregorian():   GregorianView
$dateTime->jalali():      JalaliView
$dateTime->hijri():       HijriUmmAlQuraView
$dateTime->hijriCivil():  HijriCivilView
```

### Comparison

```php
$a->equals(CivilDateTime $b):             bool
$a->lessThan(CivilDateTime $b):           bool
$a->lessThanOrEqual(CivilDateTime $b):    bool
$a->greaterThan(CivilDateTime $b):        bool
$a->greaterThanOrEqual(CivilDateTime $b): bool
$a->between(CivilDateTime $x, CivilDateTime $y, bool $inclusive = true): bool  // bounds in either order
$a->isSameDay(CivilDateTime $b):          bool

CivilDateTime::compare(CivilDateTime $a, CivilDateTime $b): int   // usort(..., CivilDateTime::compare(...))
CivilDateTime::min(CivilDateTime $first, CivilDateTime ...$rest): CivilDateTime
CivilDateTime::max(CivilDateTime $first, CivilDateTime ...$rest): CivilDateTime

$a->diffInDays(CivilDateTime $b):         int   // signed: this - other; calendar days, time ignored
$a->diffInHours(CivilDateTime $b):        int   // signed, wall-clock, truncated toward zero
$a->diffInMinutes(CivilDateTime $b):      int
$a->diffInSeconds(CivilDateTime $b):      int
```

All comparison is wall-clock time. See [concepts.md](concepts.md#civildatetime-is-wall-clock-time).

### Time arithmetic (wall-clock)

```php
$dateTime->addSeconds(int $n) / subSeconds(int $n): CivilDateTime
$dateTime->addMinutes(int $n) / subMinutes(int $n): CivilDateTime
$dateTime->addHours(int $n)   / subHours(int $n):   CivilDateTime
$dateTime->addDays(int $n)    / subDays(int $n):    CivilDateTime
$dateTime->addWeeks(int $n)   / subWeeks(int $n):   CivilDateTime
$dateTime->startOfDay():  CivilDateTime   // 00:00:00
$dateTime->endOfDay():    CivilDateTime   // 23:59:59
```

Moves the wall-clock reading, rolling over midnight. Not DST-aware — for exact elapsed time, go through `toTimestamp()`. Month and year arithmetic is calendar-specific and lives on the views. See [arithmetic.md](arithmetic.md#time-of-day-arithmetic).

### Mutation-as-new

```php
$dateTime->withJdn(int $jdn):              CivilDateTime
$dateTime->withTime(int $h, int $m, int $s): CivilDateTime
$dateTime->withTzLabel(?string $tzLabel):  CivilDateTime
```

Always returns a new `CivilDateTime`.

### Serialization

```php
$dateTime->jsonSerialize(): array{jdn: int, secondsOfDay: int, tzLabel: ?string}
CivilDateTime::fromArray(array $data): CivilDateTime
```

`CivilDateTime` implements `JsonSerializable`, so `json_encode($dateTime)` just works. `fromArray` is the inverse — validates that `jdn` is an int, `secondsOfDay` defaults to `0`, `tzLabel` defaults to `null`. Throws `InvalidArgumentException` on malformed input. See [serialization.md](serialization.md).

---

## `Eram\Daynum\CalendarView`

Interface implemented by each calendar-specific view. Views are immutable; mutator-looking methods return new views or new `CivilDateTime` values.

### Getters

```php
$view->dateTime():      CivilDateTime
$view->calendar():     Calendar
$view->year():         int
$view->month():        int        // 1-indexed
$view->day():          int        // 1-indexed
$view->hour():         int
$view->minute():       int
$view->second():       int
$view->dayOfWeek():    int        // 0..6, Sunday = 0
$view->dayOfWeekIso(): int        // 1..7, Monday = 1
$view->weekDay():      WeekDay
$view->isWeekend():    bool       // locale weekend: en Sat–Sun, fa Fri, ar Fri–Sat
$view->isWeekday():    bool
$view->dayOfYear():    int        // 1-indexed
$view->weekOfYear():     int      // ISO 8601 week — can throw WeekAtBoundaryException
$view->weekBasedYear():  int      // ISO 8601 week-based year — can throw WeekAtBoundaryException
$view->isLeapYear():   bool
$view->daysInMonth():  int
$view->daysInYear():   int
$view->toArray():      array{year:int, month:int, day:int, hour:int, minute:int, second:int, tzLabel:?string}
```

### Formatting

```php
$view->format(string $pattern): string
$view->withLocale(string $locale):  static
$view->withDigits(string $script):  static
```

See [formatting.md](formatting.md) and [localization.md](localization.md).

### Arithmetic

All arithmetic methods return `CivilDateTime`, not a view:

```php
$view->addDays(int $n):    CivilDateTime
$view->subDays(int $n):    CivilDateTime
$view->addMonths(int $n):  CivilDateTime      // clamps day-of-month
$view->subMonths(int $n):  CivilDateTime      // clamps day-of-month
$view->addYears(int $n):   CivilDateTime      // clamps day-of-month
$view->subYears(int $n):   CivilDateTime      // clamps day-of-month

$view->startOfMonth():     CivilDateTime
$view->endOfMonth():       CivilDateTime
$view->startOfYear():      CivilDateTime
$view->endOfYear():        CivilDateTime
$view->startOfWeek(WeekDay|int|null $weekStart = null): CivilDateTime   // null: locale's first day
$view->endOfWeek(WeekDay|int|null $weekStart = null):   CivilDateTime
```

See [arithmetic.md](arithmetic.md).

### Diffs

```php
$view->diffInMonths(CivilDateTime $other): int
$view->diffInYears(CivilDateTime $other):  int
```

Calendar-specific, signed, both require reaching the same day-of-month before counting. See [arithmetic.md](arithmetic.md#diffs).

### Relative time

```php
$view->diffForHumans(CivilDateTime $other): string   // "3 days ago", "in 2 hours"
$view->ago(): string                                 // diffForHumans(CivilDateTime::now($tzLabel))
```

Locale- and digit-aware; months and years in the view's calendar. See [formatting.md](formatting.md#relative-time).

### Range checks

```php
$view->isInSupportedRange(): bool
```

### Parsing (static)

```php
static GregorianView::parseExact    (string $text, string $format, ?string $tzLabel = null): CivilDateTime
static GregorianView::tryParseExact (string $text, string $format, ?string $tzLabel = null): ?CivilDateTime
static JalaliView::parseExact       (string $text, string $format, ?string $tzLabel = null): CivilDateTime
static JalaliView::tryParseExact    (string $text, string $format, ?string $tzLabel = null): ?CivilDateTime
static HijriUmmAlQuraView::parseExact    (string $text, string $format, ?string $tzLabel = null): CivilDateTime
static HijriUmmAlQuraView::tryParseExact (string $text, string $format, ?string $tzLabel = null): ?CivilDateTime
static HijriCivilView::parseExact        (string $text, string $format, ?string $tzLabel = null): CivilDateTime
static HijriCivilView::tryParseExact     (string $text, string $format, ?string $tzLabel = null): ?CivilDateTime
```

`parseExact` throws `ParseException` on failure. `tryParseExact` returns `null`. See [parsing.md](parsing.md).

---

## `Eram\Daynum\Calendar`

Low-level calendar interface — the pure math of `(year, month, day) ↔ JDN`. You rarely touch this directly; use `CivilDateTime::from*` and views. Exposed for extensibility.

```php
interface Calendar {
    public function toJdn(int $y, int $m, int $d): int;
    public function fromJdn(int $jdn): array;           // [year, month, day]
    public function isLeapYear(int $y): bool;
    public function daysInMonth(int $y, int $m): int;
    public function dayOfYear(int $y, int $m, int $d): int;
    public function monthsInYear(int $y): int;
    public function name(): string;                      // e.g. "jalali", "hijri-umalqura"
    public function localeFamily(): string;              // e.g. "jalali", "hijri"
    public function supportedRange(): array;             // [minJdn, maxJdn]
    public function supportsYear(int $y): bool;
}
```

### Calendar implementations

| Class | Identifier | Locale family | Year range |
|---|---|---|---|
| `GregorianCalendar` | `gregorian` | `gregorian` | `-9999..9999` |
| `JalaliCalendar` | `jalali` | `jalali` | `1..3177` |
| `HijriUmmAlQuraCalendar` | `hijri-umalqura` | `hijri` | `1300..1600` (table-bound) |
| `HijriCivilCalendar` | `hijri-civil` | `hijri` | `1..9666` |

Each exposes a `::instance()` singleton.

---

## `Eram\Daynum\WeekDay` enum

```php
enum WeekDay: int {
    case Monday    = 1;
    case Tuesday   = 2;
    case Wednesday = 3;
    case Thursday  = 4;
    case Friday    = 5;
    case Saturday  = 6;
    case Sunday    = 7;
}
```

Used as the `$weekStart` argument to `startOfWeek()` / `endOfWeek()` and returned by `weekDay()` and `LocaleData::firstDayOfWeek()` / `weekendDays()`. ISO 8601 numbering.

---

## Exceptions

All implement the marker interface `Eram\Daynum\Exception\DaynumException`.

| Class | SPL parent | Thrown by |
|---|---|---|
| `InvalidArgumentException` | `\InvalidArgumentException` | unknown locale/digit-script, `fromArray` bad input, week-start out of range |
| `InvalidDateException` | `\InvalidArgumentException` | invalid Y/M/D components, invalid time-of-day |
| `ParseException` | `\InvalidArgumentException` | `parseExact` failures (including wrapped calendar errors) |
| `InvalidTimezoneException` | `\RuntimeException` | `tzLabel` PHP can't resolve |
| `MissingTimezoneException` | `\RuntimeException` | tz-dependent format token on a `CivilDateTime` without `tzLabel` |
| `WeekAtBoundaryException` | `\RuntimeException` | `weekOfYear` / `weekBasedYear` / `W` / `o` at calendar boundaries |
| `UmmAlQuraOutOfRangeException` | `\OutOfRangeException` | any UAQ operation outside `Table::MIN_YEAR..MAX_YEAR` |

See [exceptions.md](exceptions.md) for full throw-sites, messages, and recovery patterns.

---

## `Eram\Daynum\Locale\LocaleRegistry`

```php
LocaleRegistry::get(string $tag): LocaleData
```

Accepts `en`, `en-us`, `fa`, `fa-ir`, `ar`, `ar-sa` (case-insensitive). Throws `InvalidArgumentException` on unknown tags. You rarely call this directly — use `$view->withLocale($tag)`.

---

## `Eram\Daynum\Formatter\DigitTransliterator`

```php
DigitTransliterator::LATN;       // 'latn'
DigitTransliterator::PERSIAN;    // 'persian'
DigitTransliterator::ARAB;       // 'arab'

DigitTransliterator::toScript(string $text, string $script): string
DigitTransliterator::toLatin(string $text): string
DigitTransliterator::isSupported(string $script): bool
```

Bidirectional digit mapping between ASCII, Persian extended (`U+06F0..06F9`), and Arabic-Indic (`U+0660..0669`). You normally call `$view->withDigits(...)` instead of touching this directly.

---

## See also

- [getting-started.md](getting-started.md)
- [concepts.md](concepts.md)
- [cookbook.md](cookbook.md)
