---
title: "Cookbook"
description: "Apply Daynum to input forms, monthly ranges and calendar fallback."
---
# Cookbook

## Recipes

Each recipe builds on a guide instead of defining a second API. Standalone examples include their expected output. The Laravel fragment requires its host application and is syntax-checked only.

## Convert a Gregorian date to Jalali (and back)

Construct once, then select the required view. The [first example](getting-started.md#first-example) displays all four calendars. Converting back uses `dateTime()->gregorian()`, not reparsing formatted display text.

## Parse user input safely with `tryParseExact`

Use a known input calendar, explicit format and, for names, explicit locale. [Parsing](parsing.md) shows invalid Esfand 30 returning null. Preserve the original input for the form and show a friendly validation message rather than passing the exception message directly to users.

## Add months at the end of month (clamping behavior)

For a monthly recurrence, decide whether the rule is “same numbered day, clamped” or “last day of every month”. Repeatedly adding one month to a clamped date drifts (January 31 → February 28 → March 28). Derive each occurrence from the original anchor with `addMonths($index)`, or explicitly call `endOfMonth()` in each target month. See the [arithmetic example](arithmetic.md#month-arithmetic-clamps-the-day).

## Query a Jalali month

Build a half-open range `[start, until)` with midnight boundaries. A timestamp-backed database should receive `$start->toTimestamp()` and `$until->toTimestamp()`; a civil store should compare day/time fields under the same zone policy. This example computes values only and makes no database writes.

```php
<?php
require 'vendor/autoload.php';

use Eram\Daynum\CivilDateTime;

$d = CivilDateTime::fromJalali(1405, 1, 19, 14, 30, 0, 'Asia/Tehran');
$start = $d->jalali()->startOfMonth()->startOfDay();
$until = $start->jalali()->addMonths(1);
echo $start->gregorian()->format('c'), "\n";
echo $until->gregorian()->format('c'), "\n";
var_export($d->greaterThanOrEqual($start) && $d->lessThan($until));
echo "\n";
```

```text
2026-03-21T00:00:00+03:30
2026-04-21T00:00:00+03:30
true
```

## Handle the Umm al-Qura range boundary (fall back to civil)

For a known Gregorian date, keep its day and choose a supported Hijri display. Include the calendar name so fallback is visible:

```php
<?php
require 'vendor/autoload.php';

use Eram\Daynum\CivilDateTime;

$d = CivilDateTime::fromGregorian(1800, 1, 1);
$view = $d->hijri();
if (!$view->isInSupportedRange()) {
    $view = $d->hijriCivil();
}
if (!$view->isInSupportedRange()) {
    throw new RuntimeException('No supported Hijri view');
}
echo $view->calendar()->name(), ': ', $view->format('Y/m/d'), "\n";
```

```text
hijri-civil: 1214/08/04
```

This does not reinterpret user-entered Umm al-Qura components as civil components.

## Add real (DST-aware) hours via timestamps

Use `CivilDateTime::fromTimestamp($d->toTimestamp() + 3600, $zone)` for one elapsed hour. The [DST example](timezones.md#doing-timezone-math) shows why `addHours(1)` can give a different wall-clock result.

## Convert a `CivilDateTime` between timezones

See [conversion versus relabeling](timezones.md#converting-between-zones). Keep a timestamp when an ambiguous repeated hour must retain its exact occurrence.

## Persist and reload a `CivilDateTime` via JSON

Use the [serialization example](serialization.md#the-json-round-trip-contract). An ORM cast should validate decoded structure before calling `fromArray()` and preserve the three fields. It should not serialize a formatted Persian date as a timestamp.

## Use Daynum in a Laravel request/response

Daynum has no framework dependency. In an application that already installs Laravel, validate the input type first, then its calendar date:

```php
use Eram\Daynum\Calendar\Jalali\JalaliView;

// Context: a Laravel request handler; Laravel is installed by the application.
$input = $request->validate(['date' => ['required', 'string']]);
$d = JalaliView::tryParseExact($input['date'], 'Y/m/d', 'Asia/Tehran');
if ($d === null) {
    throw \Illuminate\Validation\ValidationException::withMessages([
        'date' => 'Invalid Jalali date (Y/m/d).',
    ]);
}
return response()->json($d);
```

The explicit zone is an application policy. For date-only data, leave it null if no moment interpretation is intended. The JSON response contains the civil representation, not locale settings. Adapt the message to your application's language.
