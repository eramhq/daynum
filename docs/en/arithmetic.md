---
title: "Arithmetic"
description: "Add calendar units, compare values and find weeks and boundaries."
---
# Arithmetic

## Arithmetic returns `CivilDateTime`, not view

Use the calendar view for month/year operations: `$d->jalali()->addMonths(1)` counts a Jalali month. Every date-changing view method returns `CivilDateTime`, preserves the timezone label, and leaves the original untouched. Re-enter a view before calling another calendar method or `format()`.

## Month arithmetic clamps the day

`addMonths()`, `subMonths()`, `addYears()` and `subYears()` clamp a day that does not exist in the destination month to its last day. That makes addition and subtraction non-inverse at month ends. A leap-day year shift clamps too.

```php
<?php
require 'vendor/autoload.php';

use Eram\Daynum\CivilDateTime;

$d = CivilDateTime::fromGregorian(2026, 1, 31, 14, 30);
$next = $d->gregorian()->addMonths(1);
echo $next->gregorian()->format('Y-m-d H:i'), "\n";
echo $next->gregorian()->subMonths(1)->gregorian()->format('Y-m-d'), "\n";
echo $next->gregorian()->diffInMonths($d), "\n";
echo $d->gregorian()->endOfMonth()->endOfDay()->gregorian()->format('Y-m-d H:i:s'), "\n";
```

```text
2026-02-28 14:30
2026-01-28
0
2026-01-31 23:59:59
```

## Changing one part: `with()`

A view's `with(year:, month:, day:, hour:, minute:, second:)` keeps omitted/null components and validates the result. It does not clamp: January 31 with `month: 2` throws unless a valid day is supplied too. On the core value, `withTime()` replaces all three time components; `withJdn()` changes the day directly.

## Time-of-day arithmetic

The core has `add/subSeconds`, `add/subMinutes`, `add/subHours`, `add/subDays` and `add/subWeeks`; all require an integer amount, and negative amounts reverse direction. These shift wall-clock fields and ignore DST. A day is always 86400 wall-clock seconds and a week seven days. Views also have `addDays()`/`subDays()`, equivalent to the core methods. For elapsed hours use [timestamps](timezones.md#doing-timezone-math).

## Weeks

`startOfWeek()` and `endOfWeek()` use the locale's first day, or an explicit `WeekDay`/ISO integer 1–7. The argument to `endOfWeek()` is the week's **start**, not its end. Both preserve time of day. `weekDay()` returns the enum, `dayOfWeek()` uses Sunday=0, and `dayOfWeekIso()` uses Monday=1. Weekend flags follow [locale rules](localization.md).

```php
<?php
require 'vendor/autoload.php';

use Eram\Daynum\CivilDateTime;
use Eram\Daynum\WeekDay;

$v = CivilDateTime::fromGregorian(2026, 4, 8, 14, 30)->jalali()->withLocale('fa');
echo $v->startOfWeek()->gregorian()->format('Y-m-d H:i'), "\n";
echo $v->startOfWeek(WeekDay::Monday)->gregorian()->format('Y-m-d'), "\n";
echo CivilDateTime::fromGregorian(2024, 12, 30)->gregorian()->format('o-\\WW'), "\n";
```

```text
2026-04-04 14:30
2026-04-06
2025-W01
```

`weekOfYear()`/`weekBasedYear()` and `W`/`o` always use Monday/Thursday week rules in the view's own calendar, independently of locale. Use a Gregorian view for ISO Gregorian week identifiers. At a supported-range edge, a Thursday or week-based year outside the range can cause `WeekAtBoundaryException`.

## Quarters and boundaries

`quarter()` is 1–4. `startOfMonth()`, `endOfMonth()`, `startOfYear()`, `endOfYear()`, `startOfQuarter()` and `endOfQuarter()` select calendar dates and preserve time. Call `startOfDay()` or `endOfDay()` on the returned core when you need `00:00:00` or `23:59:59`. For database queries, a half-open interval from the start to the next start avoids second-precision end-point assumptions; see [cookbook](cookbook.md).

## Diffs

Differences are signed **this minus other**. Core `diffInDays()` subtracts JDNs and ignores time; seconds/minutes/hours use wall-clock readings, with minutes/hours truncated toward zero. View `diffInMonths()`/`diffInYears()` count whole calendar units using the day of month, ignoring time of day. A clamped February 28 is not a whole month after January 31 by that rule, as shown above. [Relative time](localization.md#relative-time) additionally checks the time of day.

## Comparison

`equals()`, `lessThan()`, `greaterThan()`, `lessThanOrEqual()` and `greaterThanOrEqual()` compare JDN and seconds, ignoring timezone labels. `CivilDateTime::compare()` is a `usort` callback; `min()`/`max()` require at least one value and retain the first on a tie. `between($a, $b, $inclusive = true)` accepts bounds in either order; `isSameDay()` compares only JDN. Use timestamps to compare moments across zones.

## UAQ boundary crossing via arithmetic

Day/week shifts work on JDN and can leave any supported calendar range. The core constructor does not enforce a calendar range. Check `isInSupportedRange()` on the target view before reading it. Umm al-Qura month/year shifts need table data and can throw during arithmetic itself. See the [explicit boundary example](calendars/hijri-umm-al-qura.md#uaq-boundary-crossing-via-arithmetic).
