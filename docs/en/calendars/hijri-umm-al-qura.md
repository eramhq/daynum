---
title: "Hijri Umm al-Qura"
description: "Use the bundled Umm al-Qura table and handle its range explicitly."
---
# Hijri Umm al-Qura

## Construction

`CivilDateTime::fromHijri()` and `hijri()` select Umm al-Qura; they do not select civil Hijri. Parse with `Eram\Daynum\Calendar\Hijri\HijriUmmAlQuraView`. Identifier: `hijri-umalqura`; locale family: `hijri`; default format: `Y/m/d`.

## Bundled table

The bundled [table](../../../src/Calendar/Hijri/Table.php) was generated from ICU 78.2 and covers AH 1300–1600 inclusive. Month lengths are stored as 29/30-day entries. A year is considered leap when its total is 355 days; do not assume the civil calendar's leap pattern. There is no runtime network request or `ext-intl` requirement.

## Why throw instead of silently falling back

Construction outside the table or reading an out-of-range JDN throws `UmmAlQuraOutOfRangeException`. `tryFromHijri()` returns null and `isValidHijri()` returns false; those results can also mean invalid date components, not just range failure. Parsing wraps the range error in `ParseException`; `tryParseExact()` returns null.

Choose a recovery policy explicitly. For an existing civil value, use `hijriCivil()` if that view supports its JDN, and label the result as civil. For user-entered Hijri components, switching factories changes the calendar interpretation: do that only when the input's calendar policy allows it. Do not silently “repair” an invalid Umm al-Qura date by trying another calendar. See [cookbook](../cookbook.md#handle-the-umm-al-qura-range-boundary-fall-back-to-civil).

## UAQ boundary crossing via arithmetic

Adding days changes JDN without table lookup, but reading the result may fail. Month/year arithmetic needs the destination table and can fail immediately:

```php
<?php
require 'vendor/autoload.php';

use Eram\Daynum\CivilDateTime;
use Eram\Daynum\Exception\UmmAlQuraOutOfRangeException;

$d = CivilDateTime::fromHijri(1600, 12, 29);
$next = $d->hijri()->addDays(100);
var_export($next->hijri()->isInSupportedRange());
echo "\n";
try {
    $next->hijri()->format('Y/m/d');
} catch (UmmAlQuraOutOfRangeException) {
    echo "Outside Umm al-Qura\n";
}
try {
    $d->hijri()->addYears(1);
} catch (UmmAlQuraOutOfRangeException) {
    echo "Year arithmetic needs table data\n";
}
```

```text
false
Outside Umm al-Qura
Year arithmetic needs table data
```

`isInSupportedRange()` checks the view without extracting its components. `supportedRange()` on the calendar returns inclusive JDN endpoints. Week-number calculations near those endpoints can throw `WeekAtBoundaryException`.

This table is a particular calendar model, not a prediction of local moon-sighting announcements. Choose the required calendar with your application's users. [Civil Hijri](hijri-civil.md) is a different model, not an accuracy upgrade or an unlimited fallback.
