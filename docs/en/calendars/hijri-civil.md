# Hijri — Tabular Civil (`islamic-civil`)

The deterministic 30-year arithmetic Islamic calendar with the year-16 leap variant. Identical to ICU's `islamic-civil`, Reingold–Dershowitz's "Arithmetic Islamic Calendar", and Joda-Time. Pure formula, no table, works for the full AH 1..9666 range.

## Quick facts

| | |
|---|---|
| Class | `Eram\Daynum\Calendar\Hijri\HijriCivilCalendar` |
| View | `Eram\Daynum\Calendar\Hijri\HijriCivilView` |
| Identifier | `hijri-civil` |
| Locale family | `hijri` (shared with UAQ variant) |
| Year range | `AH 1` to `AH 9666` (inclusive) |
| Epoch | JDN 1948440 = Friday 16 July 622 CE (Julian) |
| Algorithm | `(11y + 14) mod 30 < 11` leap rule, year-16 variant |
| Default format | `Y/m/d` |

## When to use this instead of UAQ

Use the civil variant for:

- **Historical research** — dates before AH 1300 (~1882 CE)
- **Far-future dates** — dates after AH 1600 (~2174 CE)
- **Round-trippable arithmetic** — civil dates never hit a table boundary
- **Systems that need a guaranteed-always-works Hijri calendar**

Use Umm al-Qura (`fromHijri` / `->hijri()`) for:

- Live display to Saudi/Gulf audiences
- Modern Persian websites that show a Hijri date next to Jalali
- Anywhere KACST's observation-adjusted month lengths matter

## Construction

```php
use Eram\Daynum\Instant;

Instant::fromHijriCivil(1447, 10, 21);
Instant::fromHijriCivil(1, 1, 1);         // earliest representable
Instant::fromHijriCivil(9666, 12, 29);    // latest representable

Instant::tryFromHijriCivil(1447, 13, 1);   // null — month 13 invalid
Instant::isValidHijriCivil(1447, 1, 30);   // true — Muharram has 30 days
```

## Accuracy vs. ICU

Across the entire 1700–2300 Gregorian fixture window, Daynum's civil implementation matches ICU's `islamic-civil` **byte-for-byte on all 219,510 days**. No divergence windows, no allow-lists.

## Leap year rule

```text
leap  ⇔  (11 * year + 14) mod 30 < 11
```

Selects the eleven canonical leap positions inside every 30-year cycle: `{2, 5, 7, 10, 13, 16, 18, 21, 24, 26, 29}`. This is the variant known as "year-16" because 16 is in the leap set. The alternative "Kuwaiti year-15" variant is not supported.

In a leap year, Dhu al-Hijjah (month 12) has 30 days; otherwise 29.

## Month lengths

| Month | Name (en) | Days |
|------:|-----------|-----:|
| 1 | Muharram | 30 |
| 2 | Safar | 29 |
| 3 | Rabiʻ I | 30 |
| 4 | Rabiʻ II | 29 |
| 5 | Jumada I | 30 |
| 6 | Jumada II | 29 |
| 7 | Rajab | 30 |
| 8 | Shaʻban | 29 |
| 9 | Ramadan | 30 |
| 10 | Shawwal | 29 |
| 11 | Dhuʻl-Qiʻdah | 30 |
| 12 | Dhuʻl-Hijjah | 29 / 30 (leap) |

Odd months have 30 days, even months have 29. Dhu al-Hijjah gets a 30th day only in leap years.

## Viewing

```php
$d = Instant::fromHijriCivil(1447, 10, 21);

$d->hijriCivil()->year();           // 1447
$d->hijriCivil()->month();          // 10
$d->hijriCivil()->day();            // 21
$d->hijriCivil()->daysInMonth();    // 29
$d->hijriCivil()->isLeapYear();     // depends on (11*1447 + 14) mod 30
$d->hijriCivil()->dayOfYear();      // 1..354 or 1..355
```

## Formatting

Both Hijri variants share a locale family (`hijri`), so month names are identical:

```php
$d = Instant::fromHijriCivil(1447, 10, 21);

$d->hijriCivil()->format('j F Y');                                  // "21 Shawwal 1447"
$d->hijriCivil()->withLocale('fa')->format('j F Y');                // "21 شوال 1447"
$d->hijriCivil()->withLocale('ar')->format('j F Y');                // "21 شوال 1447"
$d->hijriCivil()->withLocale('ar')->withDigits('arab')->format('j F Y'); // "٢١ شوال ١٤٤٧"
```

## Parsing

```php
use Eram\Daynum\Calendar\Hijri\HijriCivilView;

HijriCivilView::parseExact('1447/10/21', 'Y/m/d');
HijriCivilView::parseExact('0001/1/1', 'Y/n/j');    // Y requires 4+ digits — pad short years
```

## Why not other Islamic variants?

- `islamic-tbla` is the same calendar but with a Thursday epoch (JDN 1948439) instead of Friday (JDN 1948440). A rare regional variant Daynum does not ship in v1.
- Observational `islamic` (astronomical new-moon visibility) is non-deterministic and will never ship: pretending to compute it from a closed-form formula would be a lie.
- The "Kuwaiti year-15" leap variant is historical only; year-16 is the variant every modern library converges on.

## Range

```php
HijriCivilCalendar::EPOCH;       // 1948440
HijriCivilCalendar::MIN_YEAR;    // 1
HijriCivilCalendar::MAX_YEAR;    // 9666
```

Year 9666 AH ≈ year 10200 CE. The upper bound is inherited from Reingold–Dershowitz; PHP's 64-bit integers could represent much more, but 9666 keeps ports to narrower targets valid.

## References

- Nachum Dershowitz & Edward M. Reingold, *Calendrical Calculations* (4th ed., 2018), "Arithmetic Islamic Calendar" chapter.
- ICU `IslamicCalendar.java` (`CIVIL` type), Unicode ICU license.

## See also

- [hijri-umm-al-qura.md](hijri-umm-al-qura.md)
- [gregorian.md](gregorian.md)
- [../algorithms-and-attribution.md](../algorithms-and-attribution.md)
