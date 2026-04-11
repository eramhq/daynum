# Gregorian Calendar

The proleptic Gregorian calendar — no Julian cutover, year 0 exists, negative years are permitted.

## Quick facts

| | |
|---|---|
| Class | `Eram\Daynum\Calendar\Gregorian\GregorianCalendar` |
| View | `Eram\Daynum\Calendar\Gregorian\GregorianView` |
| Identifier | `gregorian` |
| Locale family | `gregorian` |
| Year range | `-9999` to `9999` (inclusive) |
| Algorithm | Fliegel–Van Flandern (1968/1990) |
| Default format | `Y-m-d` |

## Construction

```php
use Eram\Daynum\Instant;

Instant::fromGregorian(2026, 4, 8);
Instant::fromGregorian(2026, 4, 8, 14, 30, 0, 'Asia/Tehran');
Instant::fromGregorian(-44, 3, 15);          // Ides of March, 44 BCE
Instant::fromGregorian(0, 1, 1);             // year 0 exists

Instant::tryFromGregorian(2026, 2, 30);      // null — Feb 30 invalid
Instant::isValidGregorian(2024, 2, 29);      // true — leap year
```

## Viewing

```php
$d = Instant::fromGregorian(2026, 4, 8);

$d->gregorian()->year();              // 2026
$d->gregorian()->month();             // 4
$d->gregorian()->day();               // 8
$d->gregorian()->dayOfYear();         // 98
$d->gregorian()->daysInMonth();       // 30
$d->gregorian()->daysInYear();        // 365
$d->gregorian()->isLeapYear();        // false
$d->gregorian()->weekOfYear();        // ISO 8601 week
$d->gregorian()->weekBasedYear();     // ISO 8601 week-based year
```

## Proleptic semantics

Daynum's Gregorian calendar is **proleptic**: the rules are applied uniformly back to year `-9999`, ignoring the historical fact that the Gregorian calendar wasn't adopted until October 1582 (and not universally even then).

- Year 0 exists (unlike some astronomy conventions). 1 BC is year `0`, 2 BC is year `-1`.
- No Julian-to-Gregorian transition cutover — dates before 1582-10-15 are computed using the Gregorian leap rule, not the Julian.
- Negative years are permitted all the way to `-9999`.

```php
Instant::fromGregorian(1582, 10, 4);    // works — one day before the historical cutover
Instant::fromGregorian(1582, 10, 5);    // also works — a date that "didn't exist" historically
Instant::fromGregorian(-4713, 11, 24);  // also fine — deep historical dates
```

If you need the Julian calendar or historical Julian/Gregorian switchover math, Daynum does not provide it.

## Leap year rule

Standard Gregorian rule:

```text
leap  ⇔  (year mod 4 == 0)  ∧  (year mod 100 ≠ 0  ∨  year mod 400 == 0)
```

So: `2000` is leap, `1900` is not, `2024` is leap, `2100` is not.

## Formatting

All PHP `date()` tokens work. See [../formatting.md](../formatting.md).

```php
$d = Instant::fromGregorian(2026, 4, 8, 14, 30, 0, 'Asia/Tehran');

$d->gregorian()->format('Y-m-d');                // "2026-04-08"
$d->gregorian()->format('l, F jS Y');            // "Wednesday, April 8th 2026"
$d->gregorian()->format('c');                    // ISO 8601 composite
$d->gregorian()->format('Y-m-d H:i:s P');        // "2026-04-08 14:30:00 +03:30"
$d->gregorian()->withLocale('fa')->format('l j F Y');   // "چهارشنبه 8 آوریل 2026"
```

## Parsing

```php
use Eram\Daynum\Calendar\Gregorian\GregorianView;

GregorianView::parseExact('2026-04-08', 'Y-m-d');
GregorianView::parseExact('2026-04-08 14:30:00', 'Y-m-d H:i:s');
GregorianView::parseExact('2026-04-08 02:30 PM', 'Y-m-d h:i A');
GregorianView::parseExact('2026-04-08T14:30:00+03:30', 'c');  // composite
```

See [../parsing.md](../parsing.md) for token support.

## Range

`supportedRange()` returns the inclusive JDN range spanning `-9999-01-01` to `9999-12-31`. Practically, every reasonable application date fits; the bounds exist to make boundary checks deterministic.

```php
GregorianCalendar::MIN_YEAR;   // -9999
GregorianCalendar::MAX_YEAR;   //  9999
```

## References

- Fliegel & Van Flandern, *Communications of the ACM*, **11** (1968), 657.
- Reingold & Dershowitz, *Calendrical Calculations* (4th ed., 2018), "Gregorian" chapter.

## See also

- [jalali.md](jalali.md)
- [hijri-umm-al-qura.md](hijri-umm-al-qura.md)
- [hijri-civil.md](hijri-civil.md)
- [../arithmetic.md](../arithmetic.md)
