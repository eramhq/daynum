# Jalali (Shamsi / Solar Hijri) Calendar

The Persian solar calendar, implemented using Ahmad Birashk's 33-year arithmetic cycle — the same algorithm used by `morilog/jalali`, `jalaali-js`, and `date-fns-jalali`.

## Quick facts

| | |
|---|---|
| Class | `Daynum\Calendar\Jalali\JalaliCalendar` |
| View | `Daynum\Calendar\Jalali\JalaliView` |
| Identifier | `jalali` |
| Locale family | `jalali` |
| Year range | `1` to `3177` AP (inclusive) |
| Algorithm | Birashk 33-year cycle (port of `jalaali-js`) |
| Default format | `Y/m/d` |

## Construction

```php
use Daynum\Instant;

Instant::fromJalali(1405, 1, 19);
Instant::fromJalali(1405, 1, 19, 14, 30, 0, 'Asia/Tehran');

Instant::tryFromJalali(1405, 13, 1);    // null — no month 13
Instant::isValidJalali(1403, 12, 30);   // true — 1403 is a leap year
Instant::isValidJalali(1404, 12, 30);   // false — 1404 is not leap, Esfand has 29
```

## Month lengths

| Month | Name (en) | Name (fa) | Days |
|------:|-----------|-----------|-----:|
| 1 | Farvardin | فروردین | 31 |
| 2 | Ordibehesht | اردیبهشت | 31 |
| 3 | Khordad | خرداد | 31 |
| 4 | Tir | تیر | 31 |
| 5 | Mordad | مرداد | 31 |
| 6 | Shahrivar | شهریور | 31 |
| 7 | Mehr | مهر | 30 |
| 8 | Aban | آبان | 30 |
| 9 | Azar | آذر | 30 |
| 10 | Dey | دی | 30 |
| 11 | Bahman | بهمن | 30 |
| 12 | Esfand | اسفند | 29 / 30 (leap) |

Months 1–6 are always 31 days, 7–11 always 30, and Esfand is 29 days or 30 in a leap year.

## Jalali and ICU: a note on correctness

Daynum's Jalali calendar is the 33-year Birashk cycle ported from `jalaali-js`, the algorithm behind `morilog/jalali` and `date-fns-jalali`. This is a deliberate ecosystem choice — it's what every migrating PHP and JS developer already tests against.

Birashk is **not** identical to ICU's `persian` calendar (which uses Borkowski's arithmetic). Across the full 1700–2300 Gregorian fixture range the two algorithms agree on **99.5%** of days. The remaining 0.5% form four contiguous windows where Birashk and ICU assign the leap day to adjacent years:

| Nowruz in ICU | Nowruz in Birashk | Gregorian window       |
|---------------|-------------------|------------------------|
| 1078 AP       | (neither leap)    | 1700-01-01..1700-03-19 |
| 1177 AP       | 1176 AP           | 1797-03-21..1798       |
| 1503 AP       | 1502 AP           | 2123-03-21..2124       |
| 1602 AP       | 1601 AP           | 2222-03-21..2223       |

Within these windows Daynum is **exactly one day behind ICU**. For the ~300 years between 1800 and 2122 — the practical modern range — the two algorithms agree on every single day.

The `IcuConformanceTest` allow-list documents these windows explicitly. New divergences outside them are treated as regressions and fail the build.

See `src/Calendar/Jalali/JalaliCalendar.php` for the full class-level explanation.

## Why Birashk, not Borkowski?

Daynum prioritizes compatibility with the dominant PHP/JS Jalali ecosystem (`morilog/jalali`, `jalaali-js`, `date-fns-jalali`) over strict ICU conformance. Every migrating developer already tests against Birashk output; switching to Borkowski would break those tests without improving correctness for anyone.

## Leap year rule

Birashk defines a 33-year arithmetic cycle with pre-computed break points (see the `BREAKS` table in `JalaliCalendar.php`). `isLeapYear(1403)` is `true`; `isLeapYear(1404)` is `false`.

## Viewing

```php
$d = Instant::fromJalali(1405, 1, 19, 14, 30);

$d->jalali()->year();          // 1405
$d->jalali()->month();         // 1
$d->jalali()->day();           // 19
$d->jalali()->dayOfWeek();     // 3 (Wednesday, Sun=0)
$d->jalali()->dayOfYear();     // 19
$d->jalali()->isLeapYear();    // false
$d->jalali()->daysInMonth();   // 31
```

## Formatting

```php
$d = Instant::fromJalali(1405, 1, 19, 14, 30, 0, 'Asia/Tehran');

$d->jalali()->format('Y/m/d');                                      // "1405/01/19"
$d->jalali()->format('l j F Y');                                    // "Wednesday 19 Farvardin 1405"
$d->jalali()->withLocale('fa')->format('l j F Y');                  // "چهارشنبه 19 فروردین 1405"
$d->jalali()->withLocale('fa')->withDigits('persian')->format('Y/m/d'); // "۱۴۰۵/۰۱/۱۹"
$d->jalali()->withLocale('fa')->withDigits('persian')->format('l j F Y H:i');
// "چهارشنبه 19 فروردین 1405 14:30"
```

Arabic + Jalali formatting throws on `F` / `M` tokens because ICU's Arabic transliteration of Persian month names is low quality. Arabic-script readers who want Jalali should use `withLocale('fa')` — Persian month names render in the same Perso-Arabic script. See [../localization.md](../localization.md).

## Parsing

```php
use Daynum\Calendar\Jalali\JalaliView;

JalaliView::parseExact('1405/01/19', 'Y/m/d');
JalaliView::parseExact('۱۴۰۵/۰۱/۱۹', 'Y/m/d');     // Persian digits normalized
JalaliView::parseExact('1405/01/19 14:30', 'Y/m/d H:i');
JalaliView::tryParseExact('not-a-date', 'Y/m/d');   // null
```

Locale-dependent tokens (`F`, `M`, `l`, `D`) cannot be parsed — they are format-only. See [../parsing.md](../parsing.md).

## Range

```php
JalaliCalendar::MIN_YEAR;   // 1
JalaliCalendar::MAX_YEAR;   // 3177
```

Year 3177 AP ≈ year 3798 CE.

## References

- jalaali/jalaali-js — <https://github.com/jalaali/jalaali-js>
- Ahmad Birashk, *A New Survey of the Persian Calendar* (1993)
- `morilog/jalali` — the PHP library whose algorithm this is

## See also

- [gregorian.md](gregorian.md)
- [hijri-umm-al-qura.md](hijri-umm-al-qura.md)
- [../localization.md](../localization.md)
- [../migration-from-morilog-jalali.md](../migration-from-morilog-jalali.md)
