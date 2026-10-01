# Getting Started

A 5-minute tour of Daynum — install, construct, view, format, and do arithmetic across calendars.

## Install

```bash
composer require eram/daynum:^1.0@beta
```

Requirements: PHP 8.1 or newer. No `ext-intl` needed at runtime. Zero Composer dependencies.

## First example

```php
<?php
require 'vendor/autoload.php';

use Eram\Daynum\CivilDateTime;

$d = CivilDateTime::fromGregorian(2026, 4, 8);

echo $d->gregorian()->format('Y-m-d'), "\n";     // 2026-04-08
echo $d->jalali()->format('Y/m/d'), "\n";        // 1405/01/19
echo $d->hijri()->format('j F Y'), "\n";         // 21 Shawwal 1447
```

One `CivilDateTime`, three calendars, one format syntax.

## Construction

```php
use Eram\Daynum\CivilDateTime;

CivilDateTime::fromGregorian(2026, 4, 8);
CivilDateTime::fromGregorian(2026, 4, 8, 14, 30, 0, 'Asia/Tehran');
CivilDateTime::fromJalali(1405, 1, 19);
CivilDateTime::fromHijri(1447, 10, 21);            // Saudi Umm al-Qura
CivilDateTime::fromHijriCivil(1447, 10, 21);       // tabular, AH 1..9666
CivilDateTime::fromDateTime(new DateTimeImmutable('2026-04-08 14:30'));

CivilDateTime::now();                               // current date + time
CivilDateTime::now('Asia/Tehran');                  // current in Tehran
CivilDateTime::today();                             // today at 00:00:00
CivilDateTime::tomorrow();                          // tomorrow at 00:00:00
CivilDateTime::yesterday('Asia/Tehran');            // yesterday 00:00:00 in Tehran
```

All constructors default `hour=minute=second=0` and `tzLabel=null`.

## Safe construction

When the components may be invalid, prefer the `try*` variants that return `null` instead of throwing:

```php
CivilDateTime::tryFromJalali(1405, 13, 1);    // null — no month 13
CivilDateTime::tryFromHijri(1200, 1, 1);      // null — out of UAQ table range

CivilDateTime::isValidJalali(1405, 1, 19);    // true
CivilDateTime::isValidHijri(1447, 1, 31);     // false — Muharram has at most 30 days
```

## Views

Read components through a calendar view:

```php
$d = CivilDateTime::fromGregorian(2026, 4, 8);

$d->gregorian()->year();         // 2026
$d->jalali()->year();            // 1405
$d->jalali()->month();           // 1   (1-indexed — never 0)
$d->jalali()->day();             // 19
$d->jalali()->dayOfWeek();       // 3   (Wednesday, Sun=0)
$d->jalali()->dayOfWeekIso();    // 3   (Wednesday, Mon=1..Sun=7)
$d->jalali()->isLeapYear();      // false
$d->jalali()->daysInMonth();     // 31
$d->hijri()->year();             // 1447 — Saudi UAQ, throws if out of range
$d->hijriCivil()->year();        // 1447 — tabular, always works
```

See [concepts.md](concepts.md) for the CivilDateTime-vs-view distinction.

## Formatting

Daynum uses PHP `date()` syntax. All tokens work across all calendars.

```php
$d = CivilDateTime::fromGregorian(2026, 4, 8, 14, 30, 0, 'Asia/Tehran');

$d->gregorian()->format('Y-m-d');                                      // "2026-04-08"
$d->jalali()->format('Y/m/d');                                         // "1405/01/19"
$d->jalali()->format('l j F Y');                                       // "Wednesday 19 Farvardin 1405"
$d->jalali()->withLocale('fa')->format('l j F Y');                     // "چهارشنبه 19 فروردین 1405"
$d->jalali()->withLocale('fa')->withDigits('persian')->format('Y/m/d'); // "۱۴۰۵/۰۱/۱۹"
$d->jalali()->format('Y/m/d H:i e');                                   // "1405/01/19 14:30 Asia/Tehran"
$d->hijri()->format('j F Y');                                          // "21 Shawwal 1447"
$d->hijri()->withLocale('fa')->format('j F Y');                        // "21 شوال 1447"
$d->hijri()->withLocale('ar')->format('j F Y');                        // "21 شوال 1447"
$d->hijri()->withLocale('ar')->withDigits('arab')->format('j F Y');    // "٢١ شوال ١٤٤٧"
```

Full token reference: [formatting.md](formatting.md).

## Parsing

```php
use Eram\Daynum\Calendar\Gregorian\GregorianView;
use Eram\Daynum\Calendar\Jalali\JalaliView;
use Eram\Daynum\Calendar\Hijri\HijriUmmAlQuraView;

GregorianView::parseExact('2026-04-08', 'Y-m-d');
JalaliView::parseExact('1405/01/19', 'Y/m/d');
JalaliView::parseExact('۱۴۰۵/۰۱/۱۹', 'Y/m/d');     // Persian digits normalized
HijriUmmAlQuraView::parseExact('1447/10/21', 'Y/m/d');
GregorianView::parseExact('2026-04-08 02:30 PM', 'Y-m-d h:i A');

// Safe variant returns null instead of throwing
GregorianView::tryParseExact('not-a-date', 'Y-m-d');   // null
```

See [parsing.md](parsing.md) for which tokens parse and how the grammar handles variable-width fields.

## Arithmetic

Every arithmetic method returns a `CivilDateTime`. To format, re-enter a view:

```php
$d = CivilDateTime::fromJalali(1405, 1, 19);

$next = $d->jalali()->addDays(7);       // CivilDateTime
$next->jalali()->format('Y/m/d');       // "1405/01/26"

$d->jalali()->subMonths(1);             // CivilDateTime — one Jalali month earlier
$d->jalali()->addYears(1);              // CivilDateTime
$d->jalali()->startOfMonth();           // CivilDateTime — Farvardin 1
$d->jalali()->endOfMonth();             // CivilDateTime — Farvardin 31
$d->jalali()->startOfWeek();            // CivilDateTime — Monday by default
$d->jalali()->startOfWeek(\Eram\Daynum\WeekDay::Saturday);
```

Month arithmetic clamps the day. See [arithmetic.md](arithmetic.md).

## Comparison

```php
$a = CivilDateTime::fromGregorian(2026, 4, 8);
$b = CivilDateTime::fromGregorian(2026, 4, 10);

$a->equals($b);               // false
$a->lessThan($b);             // true
$a->diffInDays($b);           // -2 (signed)
$b->diffInDays($a);           // 2
$a->jalali()->diffInMonths($b);  // calendar-aware, whole months
```

## Serialization

```php
$d = CivilDateTime::fromJalali(1405, 1, 19, 14, 30, 0, 'Asia/Tehran');

json_encode($d);
// {"jdn":2461139,"secondsOfDay":52200,"tzLabel":"Asia/Tehran"}

$decoded = json_decode(json_encode($d), true);
$restored = CivilDateTime::fromArray($decoded);     // exact round-trip
$restored->equals($d);                         // true

$d->jalali()->toArray();
// ['year'=>1405,'month'=>1,'day'=>19,'hour'=>14,'minute'=>30,'second'=>0,'tzLabel'=>'Asia/Tehran']
```

See [serialization.md](serialization.md) for the full JSON round-trip contract and DB schema patterns.

## Escape hatch to native PHP

Need DST-aware timezone math, a Unix timestamp, or something `DateTimeImmutable` does better?

```php
$native = $d->toDateTimeImmutable();
```

See [timezones.md](timezones.md).

## Next steps

- [concepts.md](concepts.md) — the model: CivilDateTime, views, civil-not-UTC, scope
- [cookbook.md](cookbook.md) — 10+ task-indexed recipes
- [calendars/jalali.md](calendars/jalali.md) — Jalali specifics and ICU divergence
- [calendars/hijri-umm-al-qura.md](calendars/hijri-umm-al-qura.md) — UAQ range and fallback
- [api-reference.md](api-reference.md) — every method, grouped by type
