---
title: "Migrating from morilog/jalali"
description: "Map Jalalian tasks to Daynum and review behavioral differences."
---
# Migrating from morilog/jalali

Daynum is not a drop-in replacement. **morilog/jalali v3 supports immutability**, as its [v3 documentation](https://github.com/morilog/jalali/blob/v3.4.2/README.md#version-3-features) states. Migrate for the API and calendar models your application needs, not for an invented immutability, speed or accuracy advantage.

## Before / after

The left column refers to morilog v3. Check your installed version before changing call sites. Daynum examples here target beta.4. The [parsing guide](parsing.md) identifies changes since beta.3.

| morilog v3 task | Daynum equivalent |
|---|---|
| `new Jalalian($y, $m, $d)` | `CivilDateTime::fromJalali($y, $m, $d)` |
| `Jalalian::now()` / `jdate()` | `CivilDateTime::now('Asia/Tehran')->jalali()` |
| `Jalalian::forge($timestamp)` | `CivilDateTime::fromTimestamp($timestamp, 'Asia/Tehran')->jalali()` |
| `Jalalian::fromDateTime($dt)` / `fromCarbon($carbon)` | `CivilDateTime::fromDateTime($dt)->jalali()` for `DateTimeInterface` |
| `Jalalian::fromFormat('Y/m/d', $text)` | `JalaliView::parseExact($text, 'Y/m/d')` (argument order reversed) |
| `CalendarUtils::checkDate($y, $m, $d)` | `CivilDateTime::isValidJalali($y, $m, $d)` |
| `getYear()` / `getMonth()` / `getDay()` | `year()` / `month()` / `day()` on a view |
| `getMonthDays()` | `daysInMonth()` on a view |
| `getTimestamp()` | `toTimestamp()` on the core, with a timezone label |
| `toCarbon()` | `Carbon\CarbonImmutable::instance($d->toDateTimeImmutable())` if Carbon is installed |

For numeric conversion tuples, use a calendar's `fromJdn($d->jdn)` or select `year()`, `month()`, `day()` from a view. `toArray()` is an associative array with time fields, not morilog's three-element conversion tuple.

## There is no `jdate()` in Daynum

Rewrite global helper calls explicitly. Daynum beta.2 removed its earlier helpers and renamed `Instant` to `CivilDateTime` and `instant()` to `dateTime()` without aliases. See the [changelog](../../CHANGELOG.md).

## Key differences

Daynum defaults to English/Latin display. Configure both locale and digits. Its `dayOfWeek()` uses Sunday=0; morilog's `getDayOfWeek()` uses Saturday=0. Calendar arithmetic returns `CivilDateTime` and requires a new view:

```php
<?php
require 'vendor/autoload.php';

use Eram\Daynum\CivilDateTime;
use Eram\Daynum\Calendar\Jalali\JalaliView;

$d = JalaliView::parseExact('1405/01/19', 'Y/m/d', 'Asia/Tehran');
$next = $d->jalali()->addMonths(1);
echo $next->jalali()->withLocale('fa')->withDigits('persian')->format('l j F Y'), "\n";
echo $d->jalali()->format('Y/m/d'), "\n";
```

```text
شنبه ۱۹ اردیبهشت ۱۴۰۵
1405/01/19
```

Daynum uses PHP-style tokens, not percent-prefixed strftime patterns: `%A` → `l`, `%d` → `d`, `%B` → `F`, `%Y` → `Y`. Do not mechanically keep `%` characters. Parsing rejects invalid dates and does not accept relative phrases such as “next Monday”. Test month-end clamping, name parsing, weekday numbering, range errors and timezone policy in your own migration cases.

## When Carbon or native PHP is useful

Use [DateTimeImmutable](https://www.php.net/manual/en/class.datetimeimmutable.php) for native timezone conversion. [Carbon](https://github.com/CarbonPHP/carbon) extends PHP's date API with convenience methods; it offers `CarbonImmutable` as well as mutable `Carbon`. Keep it when your application depends on those APIs. Convert existing objects through `fromDateTime()` for calendar display, noting the [precision and overlap limitations](timezones.md).

Install Daynum alongside morilog while migrating. Remove morilog only after your application tests pass and no remaining dependencies or helper calls need it. No framework migration or package removal is performed by documentation examples.
