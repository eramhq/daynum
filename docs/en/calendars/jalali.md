---
title: "Jalali calendar"
description: "Convert Solar Hijri dates and account for leap years and seasons."
---
# Jalali calendar

## Construction

Use `CivilDateTime::fromJalali()` and `jalali()`; parse with `Eram\Daynum\Calendar\Jalali\JalaliView`. The identifier and locale family are `jalali`. Default string format is `Y/m/d`. Select `withLocale('fa')` for Iranian month names or `fa-AF` for Dari names; digits are a separate setting.

## Month lengths

Months 1–6 (Farvardin through Shahrivar) have 31 days; 7–11 (Mehr through Bahman) have 30; Esfand has 29 or 30 according to the leap calculation.

```php
<?php
require 'vendor/autoload.php';

use Eram\Daynum\CivilDateTime;

var_export(CivilDateTime::isValidJalali(1403, 12, 30));
echo "\n";
var_export(CivilDateTime::isValidJalali(1404, 12, 30));
echo "\n";
$v = CivilDateTime::fromJalali(1405, 8, 1)->jalali();
echo $v->withLocale('fa')->seasonName(), "\n";
echo $v->startOfQuarter()->jalali()->format('Y/m/d'), "\n";
```

```text
true
false
پاییز
1405/07/01
```

## Seasons

Only `JalaliView` supplies `season(): Season` and `seasonName(): string`. Months 1–3 are spring, 4–6 summer, 7–9 autumn and 10–12 winter. These are calendar seasons, not astronomical transition times; `startOfQuarter()` uses the first month of that group and preserves time.

## Jalali and ICU: a note on correctness

The implemented break-point table and `jalCal` calculation follow `jalaali-js`. Its [upstream attribution](https://github.com/jalaali/jalaali-js#about) credits Kazimierz M. Borkowski. Earlier Daynum prose and current source comments call this “Birashk”; that attribution should not be used to describe the code. This documentation change does not alter the algorithm.

The committed ICU fixtures and Daynum have known differences. [JalaliIcuDivergence](../../../tests/Conformance/Support/JalaliIcuDivergence.php) reconciles baseline JDN windows against the current fixture and, when present, a Node oracle. The exact baseline ranges are 2341973–2342051, 2377845–2378210, 2496914–2497279 and 2533073–2533438. Tests reject unexpected differences; this is not a claim of universal agreement with ICU, observational calendars or every upstream version. See [algorithms and attribution](../algorithms-and-attribution.md).

## Range

Construction supports years 1–3177 inclusive, exposed by `JalaliCalendar::MIN_YEAR` and `MAX_YEAR`. Use `isInSupportedRange()` before reading an arbitrary JDN. The low-level break-point computation is not a strict range validator on every reverse-conversion path.

For localized name input see [parsing](../parsing.md); `F`, `M`, `l` and `D` are supported. For month-end handling see [arithmetic](../arithmetic.md).
