---
title: "Core concepts"
description: "Understand civil time, calendar views, immutability and return types."
---
# Core concepts

## CivilDateTime and JDN

`CivilDateTime` is an immutable value with three public readonly properties: integer `jdn` (Julian Day Number), integer `secondsOfDay` (0–86399) and nullable string `tzLabel`. Here JDN identifies a calendar day, not a fractional astronomical timestamp. Time precision is whole seconds; leap seconds and microseconds are not stored.

Calendar factories validate their date ranges. The low-level constructor `new CivilDateTime($jdn, $secondsOfDay, $tzLabel)` validates only seconds of day, not the JDN's suitability for every calendar or the timezone name. Use a view's `isInSupportedRange()` before displaying results near boundaries.

## Views

`gregorian()`, `jalali()`, `hijri()` and `hijriCivil()` select how to interpret the day. They preserve time and timezone label. Each new view defaults to English and Latin digits. Creating a view does not guarantee that its date components can be read: Umm al-Qura conversion can throw when `year()` or `format()` is called.

`dateTime()` retrieves the core value. `calendar()` retrieves the calendar algorithm. `toArray()` on a view returns named date/time components; it is different from the core [JSON representation](serialization.md).

## Immutability and return types

`withLocale()` and `withDigits()` return a new view of the same type. Date arithmetic, `with()`, and boundary methods on views return `CivilDateTime`. Re-enter the desired view to continue calendar operations or formatting, and reapply locale/digits when needed. The original objects remain unchanged.

```php
<?php
require 'vendor/autoload.php';

use Eram\Daynum\CivilDateTime;

$d = CivilDateTime::fromJalali(1405, 1, 19, 14, 30);
$view = $d->jalali()->withLocale('fa')->withDigits('persian');
$next = $view->addMonths(1);
echo get_class($next), "\n";
echo $view->format('Y/m/d'), "\n";
echo $next->jalali()->format('Y/m/d H:i'), "\n";
echo $next->jalali()->withLocale('fa')->withDigits('persian')->format('Y/m/d'), "\n";
```

```text
Eram\Daynum\CivilDateTime
۱۴۰۵/۰۱/۱۹
1405/02/19 14:30
۱۴۰۵/۰۲/۱۹
```

`CivilDateTime` comparisons ignore the timezone label. Two equal wall-clock readings can represent different moments. Read [timezones](timezones.md) before using these values for event ordering or durations.

## Scope: what v1 does and doesn't ship

The current beta provides the four documented calendars and seven built-in locales. It does not provide subsecond storage, relative-date phrase parsing, holiday calendars or an ORM integration. See [limits and test scope](algorithms-and-attribution.md#limits); future calendars are not current API promises.
