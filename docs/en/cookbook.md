# Cookbook

Task-indexed recipes. Each one is copy-pasteable and runs against Daynum as shipped — no pseudocode.

## Recipes

1. [Convert a Gregorian date to Jalali (and back)](#convert-a-gregorian-date-to-jalali-and-back)
2. [Display a date in both Jalali and Hijri alongside Gregorian](#display-a-date-in-both-jalali-and-hijri-alongside-gregorian)
3. [Parse user input safely with `tryParseExact`](#parse-user-input-safely-with-tryparseexact)
4. [Format with Persian digits and Persian month names](#format-with-persian-digits-and-persian-month-names)
5. [Persist and reload an `Instant` via JSON](#persist-and-reload-an-instant-via-json)
6. [Store an `Instant` in a database (three-column pattern)](#store-an-instant-in-a-database-three-column-pattern)
7. [Add months at the end of month (clamping behavior)](#add-months-at-the-end-of-month-clamping-behavior)
8. [Handle the Umm al-Qura range boundary (fall back to civil)](#handle-the-umm-al-qura-range-boundary-fall-back-to-civil)
9. [Do timezone math by escape-hatching to `DateTimeImmutable`](#do-timezone-math-by-escape-hatching-to-datetimeimmutable)
10. [Convert an `Instant` between timezones](#convert-an-instant-between-timezones)
11. [Use Daynum in a Laravel request/response](#use-daynum-in-a-laravel-requestresponse)
12. [Migrate a `jdate()`-heavy codebase without touching call sites](#migrate-a-jdate-heavy-codebase-without-touching-call-sites)

## Convert a Gregorian date to Jalali (and back)

```php
use Daynum\Instant;

$g = Instant::fromGregorian(2026, 4, 8);
echo $g->jalali()->format('Y/m/d'), "\n";    // 1405/01/19

$j = Instant::fromJalali(1405, 1, 19);
echo $j->gregorian()->format('Y-m-d'), "\n"; // 2026-04-08
```

One `Instant`, read through different views. No conversion functions — views do the work.

## Display a date in both Jalali and Hijri alongside Gregorian

```php
$d = Instant::fromGregorian(2026, 4, 8);

printf(
    "%s   |   %s   |   %s\n",
    $d->gregorian()->format('l, F j, Y'),                        // "Wednesday, April 8, 2026"
    $d->jalali()->withLocale('fa')->format('l j F Y'),           // "چهارشنبه 19 فروردین 1405"
    $d->hijri()->format('j F Y'),                                // "21 Shawwal 1447"
);
```

## Parse user input safely with `tryParseExact`

```php
use Daynum\Calendar\Jalali\JalaliView;

function parseJalaliBirthday(string $raw): ?Instant
{
    foreach (['Y/m/d', 'Y-m-d', 'Y.m.d'] as $fmt) {
        $d = JalaliView::tryParseExact($raw, $fmt);
        if ($d !== null) {
            return $d;
        }
    }
    return null;
}

parseJalaliBirthday('۱۴۰۵/۰۱/۱۹');     // Instant — Persian digits normalized
parseJalaliBirthday('1405-01-19');    // Instant
parseJalaliBirthday('nope');          // null
```

`tryParseExact` never throws — it returns `null` on any failure. Chain the formats you want to accept.

## Format with Persian digits and Persian month names

```php
$d = Instant::fromJalali(1405, 1, 19, 14, 30);

$output = $d->jalali()
    ->withLocale('fa')
    ->withDigits('persian')
    ->format('l j F Y — H:i');
// "چهارشنبه ۱۹ فروردین ۱۴۰۵ — ۱۴:۳۰"
```

## Persist and reload an `Instant` via JSON

```php
$original = Instant::fromJalali(1405, 1, 19, 14, 30, 0, 'Asia/Tehran');

// Persist
$json = json_encode($original);
// {"jdn":2461139,"secondsOfDay":52200,"tzLabel":"Asia/Tehran"}

// Reload
$decoded = json_decode($json, true);
$restored = Instant::fromArray($decoded);

$restored->equals($original);                         // true
$restored->jalali()->format('Y/m/d H:i');             // "1405/01/19 14:30"
$restored->gregorian()->format('Y-m-d H:i');          // "2026-04-08 14:30"
```

The contract: `jdn`, `secondsOfDay`, `tzLabel`. Nothing else. See [serialization.md](serialization.md).

## Store an `Instant` in a database (three-column pattern)

```sql
CREATE TABLE events (
    id        BIGSERIAL PRIMARY KEY,
    title     TEXT NOT NULL,
    event_jdn INTEGER NOT NULL,
    event_sod INTEGER NOT NULL DEFAULT 0,
    event_tz  TEXT,
    INDEX (event_jdn)
);
```

```php
// Insert
$pdo->prepare(
    "INSERT INTO events (title, event_jdn, event_sod, event_tz) VALUES (?, ?, ?, ?)"
)->execute([$title, $d->jdn, $d->secondsOfDay, $d->tzLabel]);

// Select
$row = $pdo->query("SELECT * FROM events WHERE id = 42")->fetch();
$d = new Instant(
    jdn: (int) $row['event_jdn'],
    secondsOfDay: (int) $row['event_sod'],
    tzLabel: $row['event_tz'],
);

// Range query — pure integer comparison, index-friendly
$min = Instant::fromJalali(1405, 1, 1)->jdn;
$max = Instant::fromJalali(1405, 12, 29)->jdn;
$stmt = $pdo->prepare("SELECT * FROM events WHERE event_jdn BETWEEN ? AND ?");
$stmt->execute([$min, $max]);
```

See [serialization.md](serialization.md#db-persistence-patterns) for alternative schemas.

## Add months at the end of month (clamping behavior)

```php
// Gregorian: Jan 31 + 1 month → Feb 28/29 (clamped)
$d = Instant::fromGregorian(2026, 1, 31);
$d->gregorian()->addMonths(1);       // → 2026-02-28
$d->gregorian()->addMonths(2);       // → 2026-03-31 (no clamp needed)
$d->gregorian()->addMonths(3);       // → 2026-04-30

// Jalali: Shahrivar 31 + 1 month → Mehr 30 (clamped)
$d = Instant::fromJalali(1405, 6, 31);
$next = $d->jalali()->addMonths(1);
$next->jalali()->format('Y/m/d');    // "1405/07/30"
```

Clamping matches Carbon, `java.time`, and most mainstream date libraries. See [arithmetic.md](arithmetic.md#month-arithmetic-clamps-the-day).

## Handle the Umm al-Qura range boundary (fall back to civil)

```php
use Daynum\Exception\UmmAlQuraOutOfRangeException;

function renderHijri(Instant $d): string
{
    try {
        return $d->hijri()->format('j F Y');          // prefer UAQ
    } catch (UmmAlQuraOutOfRangeException) {
        return $d->hijriCivil()->format('j F Y') . ' (civil)';
    }
}

// Modern date: uses UAQ
renderHijri(Instant::fromGregorian(2026, 4, 8));      // "21 Shawwal 1447"

// Historical date: falls back to civil
renderHijri(Instant::fromGregorian(1500, 1, 1));      // "5 Shaʻban 905 (civil)"
```

See [calendars/hijri-umm-al-qura.md](calendars/hijri-umm-al-qura.md).

## Do timezone math by escape-hatching to `DateTimeImmutable`

Daynum does not do DST. When you need real timezone arithmetic, escape, compute, re-import.

```php
function addHours(Instant $d, int $hours): Instant
{
    $native = $d->toDateTimeImmutable()->modify("+{$hours} hours");
    return Instant::fromDateTime($native);
}

$d = Instant::fromGregorian(2026, 3, 29, 1, 30, 0, 'Europe/London');  // just before BST
$d2 = addHours($d, 1);
$d2->gregorian()->format('Y-m-d H:i T');    // "2026-03-29 03:30 BST"
```

See [timezones.md](timezones.md).

## Convert an `Instant` between timezones

```php
$tehran = Instant::fromGregorian(2026, 4, 8, 14, 30, 0, 'Asia/Tehran');

$utc = Instant::fromDateTime(
    $tehran->toDateTimeImmutable()->setTimezone(new DateTimeZone('UTC'))
);
$utc->gregorian()->format('Y-m-d H:i T');   // "2026-04-08 11:00 UTC"
```

## Use Daynum in a Laravel request/response

### Eloquent cast stub

```php
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Daynum\Instant;

class InstantCast implements CastsAttributes
{
    public function get($model, string $key, $value, array $attributes): ?Instant
    {
        if ($value === null) {
            return null;
        }
        return Instant::fromArray(json_decode($value, true));
    }

    public function set($model, string $key, $value, array $attributes): ?string
    {
        return $value === null ? null : json_encode($value);
    }
}

// In your model:
protected $casts = [
    'event_at' => InstantCast::class,
];
```

### Validation rule

```php
use Illuminate\Contracts\Validation\Rule;
use Daynum\Calendar\Jalali\JalaliView;

class ValidJalaliDate implements Rule
{
    public function passes($attribute, $value): bool
    {
        return is_string($value) && JalaliView::tryParseExact($value, 'Y/m/d') !== null;
    }

    public function message(): string
    {
        return 'The :attribute must be a valid Jalali date in Y/m/d format.';
    }
}

// In a FormRequest:
public function rules(): array
{
    return ['birthday' => ['required', new ValidJalaliDate()]];
}
```

## Migrate a `jdate()`-heavy codebase without touching call sites

Enable the opt-in `jdate()` / `gdate()` / `hdate()` helpers and existing call sites keep working unchanged, now returning `Daynum\Instant` values. See [migration-from-morilog-jalali.md](migration-from-morilog-jalali.md) for the one-line `composer.json` edit and full walkthrough.

## See also

- [faq.md](faq.md) — surprising-but-intentional design decisions
- [api-reference.md](api-reference.md) — every method at a glance
- [migration-from-morilog-jalali.md](migration-from-morilog-jalali.md) — full migration guide
