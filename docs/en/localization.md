---
title: "Localization"
description: "Choose names, week rules, relative time and digit styles independently."
---
# Localization

## Switching locale

All views default to `en`, including Jalali and Hijri. `withLocale()` changes names, AM/PM, relative-time phrases, week starts and weekend rules. It does not choose the calendar or change digits. Use `withDigits('latn')`, `withDigits('persian')` or `withDigits('arab')` separately; these are the only accepted digit-style names.

```php
<?php
require 'vendor/autoload.php';

use Eram\Daynum\CivilDateTime;
use Eram\Daynum\Locale\LocaleRegistry;

$d = CivilDateTime::fromJalali(1405, 1, 19);
echo $d->jalali()->withLocale('fa')->format('j F Y'), "\n";
echo $d->jalali()->withLocale('fa-AF')->withDigits('persian')->format('j F Y'), "\n";
echo $d->subDays(3)->jalali()->withLocale('fa')->withDigits('persian')->diffForHumans($d), "\n";
echo LocaleRegistry::get('fa_IR')->tag(), "\n";
```

```text
19 فروردین 1405
۱۹ حمل ۱۴۰۵
۳ روز پیش
fa
```

## What each locale provides

| Tag | Language | Week starts | Weekend |
|---|---|---|---|
| `en` | English | Monday | Saturday–Sunday |
| `fa` | Persian | Saturday | Friday |
| `fa-AF` | Dari | Saturday | Thursday–Friday |
| `ar` | Arabic | Sunday | Friday–Saturday |
| `ps` | Pashto | Saturday | Thursday–Friday |
| `ur` | Urdu | Sunday | Saturday–Sunday |
| `tr` | Turkish | Monday | Saturday–Sunday |

These are library locale rules, not a holiday/business-day service. `LocaleRegistry` treats case and `_`/`-` variants alike and tries less specific tags: `fa-IR` resolves to `fa`; `fa-AF` has its own data. Unknown or malformed tags throw instead of silently choosing English. `has()`, `tags()` and `normalize()` support discovery.

## The Arabic + Jalali limitation

The built-in Arabic locale has no Jalali month-name table. Formatting `F`/`M` throws `InvalidArgumentException`; parsing those names fails with `ParseException`. Numeric formats and weekday names still work. Choose `fa` for Persian month names.

## Relative time

`diffForHumans($other)` treats `$other` as the reference: an earlier value produces a past phrase. It picks the largest whole year, month, week, day, hour, minute or second. Years/months use the view calendar and check time of day too. Equal readings produce the locale's word for “now”. `ago()` compares with the current time using the stored timezone or PHP's default. These are wall-clock descriptions, not elapsed-time calculations. Keep both dates within the calendar range when calendar units are needed.

## Custom locales

Register once at application startup; the registry is process-wide and can replace built-ins. Existing views retain their locale object. Extend a built-in or implement every method in [LocaleData](../../src/Locale/LocaleData.php). A custom week start example:

```php
<?php
require 'vendor/autoload.php';

use Eram\Daynum\CivilDateTime;
use Eram\Daynum\Locale\EnglishLocale;
use Eram\Daynum\Locale\LocaleRegistry;
use Eram\Daynum\WeekDay;

LocaleRegistry::register('en-team', new class extends EnglishLocale {
    public function firstDayOfWeek(): WeekDay
    {
        return WeekDay::Sunday;
    }
});
echo CivilDateTime::fromGregorian(2026, 4, 8)->gregorian()
    ->withLocale('en-team')->startOfWeek()->gregorian()->format('Y-m-d'), "\n";
```

```text
2026-04-05
```

Locale tables preserve their source spelling, including combining marks in some names. Authored Persian prose omits those marks, but exact formatted output must stay unchanged. [DigitTransliterator](api-reference.md#digittransliterator) also converts digits in arbitrary strings. Timezone and machine-format tokens bypass digit conversion; see [formatting](formatting.md).
