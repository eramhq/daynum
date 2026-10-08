---
title: "Civil Hijri calendar"
description: "Use the tabular Islamic calendar within its documented bounds."
---
# Civil Hijri calendar

## Construction

Use `CivilDateTime::fromHijriCivil()` and `hijriCivil()`; parse with `Eram\Daynum\Calendar\Hijri\HijriCivilView`. Identifier: `hijri-civil`; locale family: `hijri`; default format: `Y/m/d`. It shares localized month names with Umm al-Qura but has different month lengths and dates.

## Leap year rule

This tabular model uses a 30-year cycle with leap positions 2, 5, 7, 10, 13, 16, 18, 21, 24, 26 and 29. The test is `(11 * year + 14) mod 30 < 11`. Odd months have 30 days, even months 29, except month 12 has 30 in a leap year. The epoch is JDN 1948440: July 19, 622 in proleptic Gregorian, or July 16 in the Julian calendar. Daynum does not provide a Julian view.

```php
<?php
require 'vendor/autoload.php';

use Eram\Daynum\CivilDateTime;

$d = CivilDateTime::fromHijriCivil(1, 1, 1);
echo $d->jdn, "\n";
echo $d->gregorian()->format('Y-m-d'), "\n";
var_export(CivilDateTime::isValidHijriCivil(9667, 1, 1));
echo "\n";
```

```text
1948440
0622-07-19
false
```

## Range

Construction supports AH 1–9666 inclusive (`HijriCivilCalendar::MIN_YEAR`/`MAX_YEAR`). It has no Umm al-Qura table dependency, but it is **not unlimited**. Use `isInSupportedRange()` when converting arbitrary JDNs; low-level reverse conversion can extrapolate beyond supported construction years.

Choose this model for deterministic tabular Hijri calculations when the application explicitly wants it, including dates outside the Umm al-Qura table. It is not a substitute for local observational or religious calendar decisions. The Thursday-epoch `islamic-tbla` variant and observational calendars are not provided.

See [Umm al-Qura](hijri-umm-al-qura.md), [arithmetic](../arithmetic.md) and [algorithm sources](../algorithms-and-attribution.md).
