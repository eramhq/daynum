---
title: "Parsing"
description: "Parse exact formats, reject invalid dates and handle failures."
---
# Parsing

## `parseExact` and `tryParseExact`

Use the concrete view class for the input calendar: `GregorianView`, `JalaliView`, `HijriUmmAlQuraView` or `HijriCivilView`. `parseExact($text, $format, ?string $tzLabel = null, ?string $locale = null)` returns `CivilDateTime`. `tryParseExact()` has the same arguments and returns `null` on `ParseException`. Invalid dates are rejected, never rolled into the next month.

```php
<?php
require 'vendor/autoload.php';

use Eram\Daynum\Calendar\Jalali\JalaliView;
use Eram\Daynum\Exception\ParseException;

$d = JalaliView::parseExact('۱۹ فروردین ۱۴۰۵', 'j F Y', locale: 'fa');
echo $d->gregorian()->format('Y-m-d'), "\n";
var_export(JalaliView::tryParseExact('1404/12/30', 'Y/m/d'));
echo "\n";
try {
    JalaliView::parseExact('1404/12/30', 'Y/m/d');
} catch (ParseException $e) {
    echo get_class($e), "\n";
}
```

```text
2026-04-08
NULL
Eram\Daynum\Exception\ParseException
```

## Parseable tokens

| Tokens | Input |
|---|---|
| `Y` | At least four digits, optional minus sign; exactly four digits before an adjacent token |
| `m d H h i s` | Exactly two digits |
| `n j G g` | One or two digits; use a literal separator before the next token |
| `F M` | Full or short month name in the parse locale |
| `l D` | Full or short weekday name, checked against the date |
| `a A` | Locale AM/PM markers, also `am`/`pm`, `ق.ظ`/`ب.ظ`, `ص`/`م` |
| `P p O` | `+HH:MM` or `Z` for P/p; `+HHMM` for O |
| `c` | Expands to `Y-m-d\TH:i:sP` in the input view's calendar |

The format must provide year, month (numeric or name) and day. Omitted time fields default to zero. `h`/`g` require an AM/PM token; avoid mixing 12- and 24-hour fields. Unsupported format-only tokens such as `y`, `U`, `W`, `o`, `e` and `r` fail. Whitespace and separators must match, and trailing input is rejected. Backslash escapes a literal token letter.

## Month and weekday names

The parse locale defaults to `en`; it does not come from a previously created view. Persian and Arabic-Indic digits normalize to Latin. Names accept full and short forms, normalize Arabic/Persian Yeh and Kaf, ignore ZWNJ and combining ezafe hamza, and fold ASCII, Latin-1 and Turkish case. This is name matching, not a general Unicode normalization API. Arabic locale lacks Jalali month names; numeric parsing still works.

## Timezone offsets

Offsets must be between `-12:00` and `+14:00` inclusive. A parsed offset overrides the `$tzLabel` argument and remains a fixed offset, not an IANA zone with DST rules. A supplied label is stored without validation until native timezone conversion needs it.

Formatting `c` is always Gregorian, but parsing `c` follows the view class. Parse machine ISO strings with `GregorianView::parseExact($text, 'c')` even if they came from a Jalali view's `format('c')`.

## Error modes

Invalid dates, range errors, mismatched weekdays and format errors become `ParseException`. An unknown locale throws Daynum's `InvalidArgumentException`, which `tryParseExact()` does not catch. Invalid PHP argument types can throw `TypeError`. See [exceptions](exceptions.md).

## Parsing changes in beta.4

The following behavior was added in beta.4. Repeated conflicting AM/PM or offset tokens now fail (beta.3 kept the last value). Equivalent offsets such as `+03:30` and `+0330` agree. Name matching now ignores Arabic vowel marks, allowing the unmarked Urdu month below, and ASCII case folding no longer depends on the process C locale. Formatted locale output is unchanged. See the [changelog](../../CHANGELOG.md#100-beta4--2026-10-08).

```php
<?php
require 'vendor/autoload.php';

use Eram\Daynum\Calendar\Gregorian\GregorianView;
use Eram\Daynum\Calendar\Hijri\HijriCivilView;

var_export(GregorianView::tryParseExact('2026-04-08 10:00 am pm', 'Y-m-d h:i a a'));
echo "\n";
var_export(GregorianView::tryParseExact('2026-04-08+03:30 +0400', 'Y-m-dP O'));
echo "\n";
echo HijriCivilView::parseExact('01 ربیع الاول 1447', 'd F Y', locale: 'ur')
    ->hijriCivil()->format('Y/m/d'), "\n";
```

```text
NULL
NULL
1447/03/01
```
