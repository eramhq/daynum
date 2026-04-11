# Arithmetic

Daynum's date arithmetic is calendar-aware, immutable, and returns `Instant` — not views. Month arithmetic clamps; year arithmetic clamps; diffs are signed and calendar-specific.

## Arithmetic returns `Instant`, not view

Since `Instant` is calendar-neutral, every arithmetic method returns a new `Instant`. To format or inspect the result, re-enter a calendar view:

```php
$next = $d->jalali()->addMonths(1);   // Instant
$next->jalali()->format('Y/m/d');     // re-enter Jalali view
```

This shape is deliberate: the arithmetic itself is calendar-specific (a "month" means different things in different calendars), but the result is calendar-agnostic.

## Available operations

```php
$view = $d->jalali();

// Day arithmetic — trivially calendar-neutral
$view->addDays(7);
$view->subDays(7);

// Month arithmetic — calendar-aware, clamps end-of-month
$view->addMonths(3);
$view->subMonths(3);

// Year arithmetic — calendar-aware, clamps end-of-month
$view->addYears(1);
$view->subYears(1);

// Boundary snapping — calendar-aware
$view->startOfMonth();
$view->endOfMonth();
$view->startOfYear();
$view->endOfYear();

// Week boundaries — ISO-configurable
$view->startOfWeek();                                // Monday start (default)
$view->startOfWeek(\Eram\Daynum\WeekDay::Saturday);       // Saturday start
$view->endOfWeek(\Eram\Daynum\WeekDay::Saturday);
```

## Month arithmetic clamps the day

When the target month doesn't have the source day, the day clamps to the target month's last day. This matches Carbon, `java.time`, and most mainstream date libraries:

```php
Instant::fromGregorian(2026, 1, 31)->gregorian()->addMonths(1);
// → Feb 28, 2026 (not Feb 31, not an error)

Instant::fromJalali(1405, 6, 31)->jalali()->addMonths(1);
// → Mehr 30, 1405 (Shahrivar is 31 days, Mehr is 30)
```

Year arithmetic clamps similarly — `2024-02-29 + 1 year` is `2025-02-28`, because 2025 isn't a leap year.

## Weeks

```php
use Eram\Daynum\WeekDay;

$view->startOfWeek();                   // Monday (default)
$view->startOfWeek(WeekDay::Monday);    // same
$view->startOfWeek(WeekDay::Saturday);  // Saturday-start week (common in Jalali contexts)
$view->startOfWeek(WeekDay::Sunday);    // US convention

$view->endOfWeek(WeekDay::Saturday);    // 6 days after startOfWeek(Saturday)
```

`WeekDay` is an enum with `Monday=1 … Sunday=7`. You can also pass an `int` in `[1, 7]` directly.

## Diffs

### `diffInDays` — on `Instant`

Signed integer days, `this - other`:

```php
$a = Instant::fromGregorian(2026, 4, 10);
$b = Instant::fromGregorian(2026, 4, 8);
$a->diffInDays($b);    //  2
$b->diffInDays($a);    // -2
```

`diffInDays` lives on `Instant` because a "day" is calendar-neutral — it's just the JDN difference.

### `diffInMonths` / `diffInYears` — on the view

Calendar-aware and signed. A month (or year) is not counted until the same day-of-month is reached in the trailing direction:

```php
$a = Instant::fromGregorian(2026, 4, 15);
$b = Instant::fromGregorian(2026, 3, 14);
$a->gregorian()->diffInMonths($b);   // 1  (day-of-month was reached)

$c = Instant::fromGregorian(2026, 3, 16);
$a->gregorian()->diffInMonths($c);   // 0  (still in same "month" from $c's POV)
```

The same logic applies to `diffInYears` — counting requires both month and day-of-month to be reached.

Different calendars can give different answers for the same `Instant` pair:

```php
$a->jalali()->diffInMonths($b);      // may differ from the Gregorian count
```

## UAQ boundary crossing via arithmetic

Arithmetic on a UAQ view produces a calendar-neutral `Instant`. Viewing the result in Hijri Umm al-Qura may throw if the new date is outside the table range (AH 1300–1600). Use `hijriCivil()` as a fallback:

```php
use Eram\Daynum\Exception\UmmAlQuraOutOfRangeException;

$d = Instant::fromHijri(1600, 12, 29);     // near table edge
$result = $d->hijri()->addDays(100);        // returns Instant (no error)

$result->hijriCivil()->year();              // works — civil has no range limit

try {
    $result->hijri()->year();
} catch (UmmAlQuraOutOfRangeException) {
    // arithmetic moved us out of the bundled table
}
```

See [calendars/hijri-umm-al-qura.md](calendars/hijri-umm-al-qura.md).

## Supported-range checks

```php
$view->isInSupportedRange();    // bool
```

Use this when building queries that could reach a calendar's edge. If an arithmetic chain could push you into a gray zone, check before reading components.

## Comparison

```php
$a->equals($b);
$a->lessThan($b);
$a->lessThanOrEqual($b);
$a->greaterThan($b);
$a->greaterThanOrEqual($b);
```

All five are methods on `Instant` — not calendar-specific — because ordering only cares about the JDN and the time-of-day, not which calendar you happened to enter.

Remember: comparison is civil, not UTC. Two `Instant` objects with the same JDN/time but different `tzLabel` values are `equals()` even though they represent different physical moments. See [concepts.md](concepts.md#instant-is-civil-not-utc).

## See also

- [concepts.md](concepts.md) — the Instant-vs-view split that makes this work
- [calendars/hijri-umm-al-qura.md](calendars/hijri-umm-al-qura.md) — boundary handling
- [cookbook.md](cookbook.md#add-months-at-the-end-of-month) — end-of-month clamping worked example
