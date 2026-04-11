# API Reference

Hand-written, grouped by type. For narrative docs see [getting-started.md](getting-started.md), [concepts.md](concepts.md), and the topic pages.

**Jump to:** [`Instant`](#daynuminstant) · [`CalendarView`](#daynumcalendarview) · [`Calendar`](#daynumcalendar) · [`WeekDay`](#daynumweekday-enum) · [Exceptions](#exceptions) · [`LocaleRegistry`](#daynumlocalelocaleregistry) · [`DigitTransliterator`](#daynumformatterdigittransliterator) · [Helpers](#opt-in-helpers-srchelpersphp)

## `Daynum\Instant`

The immutable calendar-agnostic core value. Triple of `(jdn, secondsOfDay, tzLabel)`. See [concepts.md](concepts.md).

### Properties

| | | |
|---|---|---|
| `jdn` | `int` | Julian Day Number |
| `secondsOfDay` | `int` | `[0, 86400)` |
| `tzLabel` | `?string` | IANA name, fixed offset, or `null` |

### Constructor

```php
new Instant(int $jdn, int $secondsOfDay = 0, ?string $tzLabel = null)
```

Throws `InvalidDateException` if `secondsOfDay` is out of range.

### Construction from calendar components

```php
Instant::fromGregorian(int $y, int $m, int $d, int $h=0, int $min=0, int $s=0, ?string $tz=null): Instant
Instant::fromJalali   (int $y, int $m, int $d, int $h=0, int $min=0, int $s=0, ?string $tz=null): Instant
Instant::fromHijri    (int $y, int $m, int $d, int $h=0, int $min=0, int $s=0, ?string $tz=null): Instant
Instant::fromHijriCivil(int $y, int $m, int $d, int $h=0, int $min=0, int $s=0, ?string $tz=null): Instant
```

`fromHijri` is Saudi Umm al-Qura; `fromHijriCivil` is the tabular `islamic-civil` variant. See [calendars/hijri-umm-al-qura.md](calendars/hijri-umm-al-qura.md) and [calendars/hijri-civil.md](calendars/hijri-civil.md).

### Safe construction

```php
Instant::tryFromGregorian(...): ?Instant
Instant::tryFromJalali(...):    ?Instant
Instant::tryFromHijri(...):     ?Instant
Instant::tryFromHijriCivil(...): ?Instant

Instant::isValidGregorian(int $y, int $m, int $d): bool
Instant::isValidJalali(int $y, int $m, int $d):    bool
Instant::isValidHijri(int $y, int $m, int $d):     bool
Instant::isValidHijriCivil(int $y, int $m, int $d): bool
```

`tryFrom*` return `null` on invalid input instead of throwing. `isValid*` is equivalent to `tryFrom*(...) !== null`.

### Interop

```php
Instant::fromDateTime(DateTimeInterface $dt): Instant
$instant->toDateTimeImmutable(): DateTimeImmutable
```

`fromDateTime` reads the proleptic Gregorian date, time-of-day, and timezone name. `toDateTimeImmutable` is the escape hatch for real timezone math. See [timezones.md](timezones.md).

### Current-date helpers

```php
Instant::now(?string $tzLabel = null):       Instant   // current date + time
Instant::today(?string $tzLabel = null):     Instant   // today at 00:00:00
Instant::tomorrow(?string $tzLabel = null):  Instant   // tomorrow at 00:00:00
Instant::yesterday(?string $tzLabel = null): Instant   // yesterday at 00:00:00
```

When `$tzLabel` is `null`, resolves from `date_default_timezone_get()` and stores it on the result.

### Calendar views

```php
$instant->gregorian():   GregorianView
$instant->jalali():      JalaliView
$instant->hijri():       HijriUmmAlQuraView
$instant->hijriCivil():  HijriCivilView
```

### Comparison

```php
$a->equals(Instant $b):             bool
$a->lessThan(Instant $b):           bool
$a->lessThanOrEqual(Instant $b):    bool
$a->greaterThan(Instant $b):        bool
$a->greaterThanOrEqual(Instant $b): bool
$a->diffInDays(Instant $b):         int   // signed: this - other
```

All comparison is civil-time. See [concepts.md](concepts.md#instant-is-civil-not-utc).

### Mutation-as-new

```php
$instant->withJdn(int $jdn):              Instant
$instant->withTime(int $h, int $m, int $s): Instant
$instant->withTzLabel(?string $tzLabel):  Instant
```

Always returns a new `Instant`.

### Serialization

```php
$instant->jsonSerialize(): array{jdn: int, secondsOfDay: int, tzLabel: ?string}
Instant::fromArray(array $data): Instant
```

`Instant` implements `JsonSerializable`, so `json_encode($instant)` just works. `fromArray` is the inverse — validates that `jdn` is an int, `secondsOfDay` defaults to `0`, `tzLabel` defaults to `null`. Throws `InvalidArgumentException` on malformed input. See [serialization.md](serialization.md).

---

## `Daynum\CalendarView`

Interface implemented by each calendar-specific view. Views are immutable; mutator-looking methods return new views or new `Instant` values.

### Getters

```php
$view->instant():      Instant
$view->calendar():     Calendar
$view->year():         int
$view->month():        int        // 1-indexed
$view->day():          int        // 1-indexed
$view->hour():         int
$view->minute():       int
$view->second():       int
$view->dayOfWeek():    int        // 0..6, Sunday = 0
$view->dayOfWeekIso(): int        // 1..7, Monday = 1
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

All arithmetic methods return `Instant`, not a view:

```php
$view->addDays(int $n):    Instant
$view->subDays(int $n):    Instant
$view->addMonths(int $n):  Instant      // clamps day-of-month
$view->subMonths(int $n):  Instant      // clamps day-of-month
$view->addYears(int $n):   Instant      // clamps day-of-month
$view->subYears(int $n):   Instant      // clamps day-of-month

$view->startOfMonth():     Instant
$view->endOfMonth():       Instant
$view->startOfYear():      Instant
$view->endOfYear():        Instant
$view->startOfWeek(WeekDay|int $weekStart = WeekDay::Monday): Instant
$view->endOfWeek(WeekDay|int $weekStart = WeekDay::Monday):   Instant
```

See [arithmetic.md](arithmetic.md).

### Diffs

```php
$view->diffInMonths(Instant $other): int
$view->diffInYears(Instant $other):  int
```

Calendar-specific, signed, both require reaching the same day-of-month before counting. See [arithmetic.md](arithmetic.md#diffs).

### Range checks

```php
$view->isInSupportedRange(): bool
```

### Parsing (static)

```php
static GregorianView::parseExact    (string $text, string $format, ?string $tzLabel = null): Instant
static GregorianView::tryParseExact (string $text, string $format, ?string $tzLabel = null): ?Instant
static JalaliView::parseExact       (string $text, string $format, ?string $tzLabel = null): Instant
static JalaliView::tryParseExact    (string $text, string $format, ?string $tzLabel = null): ?Instant
static HijriUmmAlQuraView::parseExact    (string $text, string $format, ?string $tzLabel = null): Instant
static HijriUmmAlQuraView::tryParseExact (string $text, string $format, ?string $tzLabel = null): ?Instant
static HijriCivilView::parseExact        (string $text, string $format, ?string $tzLabel = null): Instant
static HijriCivilView::tryParseExact     (string $text, string $format, ?string $tzLabel = null): ?Instant
```

`parseExact` throws `ParseException` on failure. `tryParseExact` returns `null`. See [parsing.md](parsing.md).

---

## `Daynum\Calendar`

Low-level calendar interface — the pure math of `(year, month, day) ↔ JDN`. You rarely touch this directly; use `Instant::from*` and views. Exposed for extensibility.

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

## `Daynum\WeekDay` enum

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

Used as the `$weekStart` argument to `startOfWeek()` / `endOfWeek()`. ISO 8601 numbering.

---

## Exceptions

All implement the marker interface `Daynum\Exception\DaynumException`.

| Class | SPL parent | Thrown by |
|---|---|---|
| `InvalidArgumentException` | `\InvalidArgumentException` | unknown locale/digit-script, `fromArray` bad input, week-start out of range |
| `InvalidDateException` | `\InvalidArgumentException` | invalid Y/M/D components, invalid time-of-day |
| `ParseException` | `\InvalidArgumentException` | `parseExact` failures (including wrapped calendar errors) |
| `InvalidTimezoneException` | `\RuntimeException` | `tzLabel` PHP can't resolve |
| `MissingTimezoneException` | `\RuntimeException` | tz-dependent format token on an `Instant` without `tzLabel` |
| `WeekAtBoundaryException` | `\RuntimeException` | `weekOfYear` / `weekBasedYear` / `W` / `o` at calendar boundaries |
| `UmmAlQuraOutOfRangeException` | `\OutOfRangeException` | any UAQ operation outside `Table::MIN_YEAR..MAX_YEAR` |

See [exceptions.md](exceptions.md) for full throw-sites, messages, and recovery patterns.

---

## `Daynum\Locale\LocaleRegistry`

```php
LocaleRegistry::get(string $tag): LocaleData
```

Accepts `en`, `en-us`, `fa`, `fa-ir`, `ar`, `ar-sa` (case-insensitive). Throws `InvalidArgumentException` on unknown tags. You rarely call this directly — use `$view->withLocale($tag)`.

---

## `Daynum\Formatter\DigitTransliterator`

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

## Opt-in helpers (`src/helpers.php`)

```php
gdate(int $y, int $m, int $d, int $h=0, int $min=0, int $s=0, ?string $tz=null): Instant
jdate(int $y, int $m, int $d, int $h=0, int $min=0, int $s=0, ?string $tz=null): Instant
hdate(int $y, int $m, int $d, int $h=0, int $min=0, int $s=0, ?string $tz=null): Instant
```

Thin wrappers around `Instant::fromGregorian`, `fromJalali`, and `fromHijri`. Each is wrapped in `function_exists()` so Daynum will never silently override a name your application already defines. Not autoloaded by default — see [migration-from-morilog-jalali.md](migration-from-morilog-jalali.md) for the `composer.json` opt-in.

---

## See also

- [getting-started.md](getting-started.md)
- [concepts.md](concepts.md)
- [cookbook.md](cookbook.md)
