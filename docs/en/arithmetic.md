# Arithmetic

Daynum's date arithmetic is calendar-aware, immutable, and returns `CivilDateTime` — not views. Month arithmetic clamps; year arithmetic clamps; diffs are signed and calendar-specific.

## Arithmetic returns `CivilDateTime`, not view

Since `CivilDateTime` is calendar-neutral, every arithmetic method returns a new `CivilDateTime`. To format or inspect the result, re-enter a calendar view:

```php
$next = $d->jalali()->addMonths(1);   // CivilDateTime
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

## Time-of-day arithmetic

Seconds, minutes, hours, days and weeks don't depend on the calendar, so they live on `CivilDateTime` itself:

```php
$d = CivilDateTime::fromJalali(1405, 1, 19, 22, 15, 0, 'Asia/Tehran');

$d->addHours(3);        // 1405/01/20 01:15 — rolls over midnight
$d->subMinutes(90);
$d->addSeconds(30);
$d->addWeeks(2);
$d->addDays(1);         // same as $d->jalali()->addDays(1)

$d->startOfDay();       // 00:00:00, same day
$d->endOfDay();         // 23:59:59, same day
```

This is **wall-clock** arithmetic: it moves the reading on the clock face and ignores the timezone. On a night when the clocks spring forward, `01:30 + 1 hour` is still `02:30`, a time that doesn't exist in that zone. When you need exact elapsed time, go through a timestamp:

```php
$exact = CivilDateTime::fromTimestamp($d->toTimestamp() + 3600, $d->tzLabel ?? 'UTC');
```

See [timezones.md](timezones.md#doing-timezone-math).

## Month arithmetic clamps the day

When the target month doesn't have the source day, the day clamps to the target month's last day. This matches Carbon, `java.time`, and most mainstream date libraries:

```php
CivilDateTime::fromGregorian(2026, 1, 31)->gregorian()->addMonths(1);
// → Feb 28, 2026 (not Feb 31, not an error)

CivilDateTime::fromJalali(1405, 6, 31)->jalali()->addMonths(1);
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

### `diffInDays` — on `CivilDateTime`

Signed integer days, `this - other`:

```php
$a = CivilDateTime::fromGregorian(2026, 4, 10);
$b = CivilDateTime::fromGregorian(2026, 4, 8);
$a->diffInDays($b);    //  2
$b->diffInDays($a);    // -2
```

`diffInDays` lives on `CivilDateTime` because a "day" is calendar-neutral — it's just the JDN difference. It counts calendar days and ignores the time of day: 23:00 → 01:00 the next morning is 1 day.

### `diffInHours` / `diffInMinutes` / `diffInSeconds` — on `CivilDateTime`

Signed wall-clock differences, truncated toward zero:

```php
$a = CivilDateTime::fromGregorian(2026, 4, 9, 1, 0, 0);
$b = CivilDateTime::fromGregorian(2026, 4, 8, 22, 30, 30);
$a->diffInSeconds($b);   //  8970
$a->diffInMinutes($b);   //   149
$a->diffInHours($b);     //     2
$b->diffInHours($a);     //    -2
```

Like the arithmetic above, these ignore DST. For exact elapsed seconds, subtract timestamps: `$a->toTimestamp() - $b->toTimestamp()`.

### `diffInMonths` / `diffInYears` — on the view

Calendar-aware and signed. A month (or year) is not counted until the same day-of-month is reached in the trailing direction:

```php
$a = CivilDateTime::fromGregorian(2026, 4, 15);
$b = CivilDateTime::fromGregorian(2026, 3, 14);
$a->gregorian()->diffInMonths($b);   // 1  (day-of-month was reached)

$c = CivilDateTime::fromGregorian(2026, 3, 16);
$a->gregorian()->diffInMonths($c);   // 0  (still in same "month" from $c's POV)
```

The same logic applies to `diffInYears` — counting requires both month and day-of-month to be reached.

Because `addMonths` clamps and `diffInMonths` waits for the day-of-month, the two are not always inverse at month ends:

```php
$jan31 = CivilDateTime::fromGregorian(2026, 1, 31);
$feb28 = $jan31->gregorian()->addMonths(1);    // Feb 28 (clamped)
$feb28->gregorian()->diffInMonths($jan31);      // 0 — day 28 hasn't reached day 31
```

For days 1–28 they always round-trip: `$v->addMonths($n)` diffed back against `$v` returns `$n`.

Different calendars can give different answers for the same `CivilDateTime` pair:

```php
$a->jalali()->diffInMonths($b);      // may differ from the Gregorian count
```

## UAQ boundary crossing via arithmetic

Arithmetic on a UAQ view produces a calendar-neutral `CivilDateTime`. Viewing the result in Hijri Umm al-Qura may throw if the new date is outside the table range (AH 1300–1600). Use `hijriCivil()` as a fallback:

```php
use Eram\Daynum\Exception\UmmAlQuraOutOfRangeException;

$d = CivilDateTime::fromHijri(1600, 12, 29);     // near table edge
$result = $d->hijri()->addDays(100);        // returns CivilDateTime (no error)

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

$a->between($lo, $hi);                    // inclusive; bounds in either order
$a->between($lo, $hi, inclusive: false);
$a->isSameDay($b);                        // same calendar day, any time

usort($dates, CivilDateTime::compare(...));   // sort ascending
CivilDateTime::min($a, $b, $c);               // earliest
CivilDateTime::max($a, $b, $c);               // latest
```

All of these are on `CivilDateTime` — not calendar-specific — because ordering only cares about the JDN and the time-of-day, not which calendar you happened to enter.

Remember: comparison is wall-clock, not UTC. Two `CivilDateTime` objects with the same JDN/time but different `tzLabel` values are `equals()` even though they represent different physical moments. See [concepts.md](concepts.md#civildatetime-is-wall-clock-time).

## See also

- [concepts.md](concepts.md) — the CivilDateTime-vs-view split that makes this work
- [calendars/hijri-umm-al-qura.md](calendars/hijri-umm-al-qura.md) — boundary handling
- [cookbook.md](cookbook.md#add-months-at-the-end-of-month) — end-of-month clamping worked example
