---
title: "Serialization"
description: "Persist civil values without confusing component arrays with JSON."
---
# Serialization

## The JSON round-trip contract

`CivilDateTime` implements `JsonSerializable`; its JSON has `jdn`, `secondsOfDay` and `tzLabel`. It does not remember the originating calendar, locale or digit style. Save those separately if they are part of your domain.

```php
<?php
require 'vendor/autoload.php';

use Eram\Daynum\CivilDateTime;

$d = CivilDateTime::fromGregorian(2026, 4, 8, 14, 30, 0, 'Asia/Tehran');
$json = json_encode($d, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
echo $json, "\n";
$copy = CivilDateTime::fromArray(json_decode($json, true, flags: JSON_THROW_ON_ERROR));
var_export($copy->jsonSerialize() === $d->jsonSerialize());
echo "\n";
echo $copy->jalali()->format('Y/m/d'), "\n";
```

```text
{"jdn":2461139,"secondsOfDay":52200,"tzLabel":"Asia/Tehran"}
true
1405/01/19
```

## Reconstructing

`fromArray()` requires an integer `jdn`. Missing or null `secondsOfDay` becomes 0; otherwise it must be an integer from 0 to 86399. Missing/null `tzLabel` becomes null; otherwise it must be a string. Numeric strings are not integers. Extra keys are ignored. The timezone name and calendar range are not checked here; validate them for the intended use. Invalid shapes throw Daynum's `InvalidArgumentException`; invalid seconds throw `InvalidDateException`. JSON decoding has its own `JsonException` and shape/type checks.

Do not use `equals()` to verify all serialized fields: it ignores timezone labels. The example compares the serialized arrays instead.

## Calendar-specific array form

A view's `toArray()` returns `year`, `month`, `day`, `hour`, `minute`, `second`, `tzLabel`, with components in that calendar. It has no `jdn` and cannot be passed to `CivilDateTime::fromArray()`. To rebuild it, select the correct `fromGregorian`/`fromJalali`/`fromHijri`/`fromHijriCivil` factory and map those fields explicitly.

## DB persistence patterns

Use three typed columns (integer JDN, integer seconds, nullable string label) or a JSON column for civil schedules. A database driver may return integers as strings; validate and cast at that boundary. Store the calendar identity too for rules such as “every Jalali month”. For actual event moments, persist UTC timestamps with any required original-zone metadata. A bare SQL datetime string loses the label; adding one later is an interpretation, not a recovered instant. Daynum does not ship ORM casts. See [recipes](cookbook.md) and [timezone limitations](timezones.md).
