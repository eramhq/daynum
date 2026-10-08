---
title: "Gregorian calendar"
description: "Use proleptic Gregorian dates and understand year zero and bounds."
---
# Gregorian calendar

## Construction

Use `CivilDateTime::fromGregorian()` and `gregorian()`; parse with `Eram\Daynum\Calendar\Gregorian\GregorianView`. The calendar identifier and locale family are both `gregorian`. Default string format is `Y-m-d`.

## Proleptic semantics

Daynum applies the Gregorian leap rule to all supported years, including dates before its historical adoption. It does not model a Julian/Gregorian cutover or regional skipped dates. Astronomical year numbering includes year 0 (1 BCE); year -1 represents 2 BCE.

## Leap year rule

A year divisible by 4 is leap unless divisible by 100 but not 400.

```php
<?php
require 'vendor/autoload.php';

use Eram\Daynum\CivilDateTime;

var_export(CivilDateTime::isValidGregorian(1900, 2, 29));
echo "\n";
var_export(CivilDateTime::isValidGregorian(2000, 2, 29));
echo "\n";
echo CivilDateTime::fromGregorian(0, 1, 1)->gregorian()->format('Y-m-d'), "\n";
```

```text
false
true
0000-01-01
```

## Range

`GregorianCalendar::MIN_YEAR` is -9999 and `MAX_YEAR` is 9999, inclusive for construction. `supportedRange()` returns the corresponding inclusive JDN bounds. Low-level reverse conversion and JDN arithmetic can produce values outside those construction limits; check `isInSupportedRange()` rather than relying on every accessor to throw. Integer/timestamp limits of the PHP platform still apply.

Read [formatting](../formatting.md), [arithmetic](../arithmetic.md) and [algorithms](../algorithms-and-attribution.md).
