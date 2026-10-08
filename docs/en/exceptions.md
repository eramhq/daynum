---
title: "Errors and exceptions"
description: "Handle date, parsing, timezone and range errors at the right boundary."
---
# Errors and exceptions

All library-defined exceptions live in `Eram\Daynum\Exception` and implement the `DaynumException` marker interface. Catch a specific class for recovery; use the marker at an application boundary when all Daynum failures share a policy. It does not include PHP `TypeError`, JSON errors or exceptions thrown by your own locale implementations.

## Exception reference

| Exception | Typical trigger | Handling |
|---|---|---|
| `InvalidDateException` | Invalid date/time or construction year | Reject/correct input; use `tryFrom…()` for nullable validation |
| `ParseException` | Format mismatch, invalid parsed date, range or weekday | Show input guidance; `tryParseExact()` returns null |
| `InvalidArgumentException` | Unknown locale/digit style, invalid week start or array shape | Fix configuration or input mapping |
| `MissingTimezoneException` | Timestamp/timezone token with no label | Supply the intended zone, not an arbitrary server default |
| `InvalidTimezoneException` | Native conversion with an unknown timezone | Validate application zone selection |
| `UmmAlQuraOutOfRangeException` | Construction, calendar reading or month/year arithmetic beyond the table | Explicitly select another calendar or reject |
| `WeekAtBoundaryException` | Week calculation needs an unsupported Thursday/year | Omit the week label or handle that boundary |

## Invalid-date parsing

`parseExact()` wraps calendar exceptions, including Umm al-Qura range errors, in `ParseException`. `tryParseExact()` catches only that class, so an unknown locale still throws:

```php
<?php
require 'vendor/autoload.php';

use Eram\Daynum\Calendar\Gregorian\GregorianView;
use Eram\Daynum\Exception\DaynumException;

try {
    GregorianView::tryParseExact('2026-04-08', 'Y-m-d', locale: 'xx');
} catch (DaynumException $e) {
    echo get_class($e), "\n";
}
```

```text
Eram\Daynum\Exception\InvalidArgumentException
```

## UmmAlQuraOutOfRangeException

Do not use `isValidHijri() === false` as proof that a date merely needs a civil fallback: invalid months/days produce the same result. See [explicit calendar handling](calendars/hijri-umm-al-qura.md).

The library's `InvalidArgumentException`, `InvalidDateException` and `ParseException` extend PHP's `\InvalidArgumentException`; the latter two are not subclasses of Daynum's own `InvalidArgumentException`. Catch the marker or the intended concrete types. Exact exception messages are diagnostic text, not a structured error-code API.
