---
title: "Timezones"
description: "Distinguish timezone labels, instant conversion and elapsed time."
---
# Timezones

## What `tzLabel` does and doesn't do

A label records how a wall-clock reading should be interpreted when native PHP needs a timezone. `withTzLabel()` changes only that string; it neither changes the clock fields nor preserves the represented instant. Calendar conversion also keeps the clock fields unchanged.

## Converting between zones

Convert a moment through timestamps, as below, or call `setTimezone(new DateTimeZone(...))` on `toDateTimeImmutable()` and then `CivilDateTime::fromDateTime()`.

```php
<?php
require 'vendor/autoload.php';

use Eram\Daynum\CivilDateTime;

$d = CivilDateTime::fromGregorian(2026, 4, 8, 12, 0, 0, 'UTC');
$relabeled = $d->withTzLabel('Asia/Tehran');
$converted = CivilDateTime::fromTimestamp($d->toTimestamp(), 'Asia/Tehran');
echo $relabeled->gregorian()->format('H:i P'), "\n";
echo $converted->gregorian()->format('H:i P'), "\n";
var_export($d->equals($relabeled));
echo "\n";
echo $relabeled->toTimestamp() - $d->toTimestamp(), "\n";
```

```text
12:00 +03:30
15:30 +03:30
true
-12600
```

The equal readings above represent different moments. Core comparison ignores labels; timestamp comparison does not.

## Doing timezone math

Calendar arithmetic expresses a schedule in civil fields. Exact elapsed time belongs on a timeline. At the New York spring DST transition:

```php
<?php
require 'vendor/autoload.php';

use Eram\Daynum\CivilDateTime;

$d = CivilDateTime::fromGregorian(2026, 3, 8, 1, 30, 0, 'America/New_York');
$wall = $d->addHours(1);
$elapsed = CivilDateTime::fromTimestamp($d->toTimestamp() + 3600, 'America/New_York');
echo $wall->gregorian()->format('Y-m-d H:i'), "\n";
echo $elapsed->gregorian()->format('Y-m-d H:i'), "\n";
echo $elapsed->diffInHours($d), "\n";
echo $elapsed->toTimestamp() - $d->toTimestamp(), "\n";
```

```text
2026-03-08 02:30
2026-03-08 03:30
2
3600
```

The civil result `02:30` is a nonexistent local reading that Daynum can store. Converting it to native PHP resolves the gap. Use integer timestamp addition for an exact number of elapsed seconds; a local day need not be 24 elapsed hours.

## The escape hatch: `toDateTimeImmutable()`

The adapter interprets Gregorian date fields and the label through PHP's timezone database. A missing label uses PHP's default timezone here, whereas `toTimestamp()` and timezone format tokens require an explicit label and throw `MissingTimezoneException` when absent. `now()`/`today()` use and store the default timezone when none is supplied; ordinary calendar factories leave it null.

Invalid labels can be stored by calendar factories or `withTzLabel()`; native conversion rejects them with `InvalidTimezoneException`. A fixed offset such as `+03:30` has no DST transitions. A region such as `America/New_York` has date-dependent rules.

Spring-forward gaps normalize forward. The tested fall-back overlap resolves to the earlier occurrence; the civil value has no “fold” field to distinguish both occurrences. Consequently timestamp → civil → timestamp can lose the later occurrence of a repeated local time. `fromDateTime()` also discards microseconds. Keep an original timestamp or native object if either distinction matters. Native timezone results depend on the installed PHP timezone database. See [boundary tests](../../tests/Unit/CivilDateTimeBoundaryTest.php).
