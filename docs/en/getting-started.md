---
title: "Getting started"
description: "Install Daynum and run a complete calendar conversion example."
---
# Getting started

## Install

You need PHP 8.1+ and Composer. Production use needs no `ext-intl` and no other Composer packages. Development checks use the dev dependencies in [composer.json](../../composer.json).

Install the currently published beta explicitly:

```sh
composer require eram/daynum:1.0.0-beta.4
```

To accept future compatible beta releases, use `composer require 'eram/daynum:^1.0@beta'` and review the resolved version and changelog. Neither command installs unreleased branch changes. See [release status](overview.md#release-status).

## First example

Save this as `example.php` beside `vendor/` and run `php example.php`. All complete examples in these guides run from the project root and print the following `text` block exactly.

```php
<?php
require 'vendor/autoload.php';

use Eram\Daynum\CivilDateTime;

$d = CivilDateTime::fromGregorian(2026, 4, 8, 14, 30, 0, 'Asia/Tehran');
echo $d->gregorian()->format('Y-m-d H:i'), "\n";
echo $d->jalali()->withLocale('fa')->withDigits('persian')->format('l j F Y'), "\n";
echo $d->hijri()->format('Y/m/d'), "\n";
echo $d->hijriCivil()->format('Y/m/d'), "\n";
```

```text
2026-04-08 14:30
چهارشنبه ۱۹ فروردین ۱۴۰۵
1447/10/20
1447/10/20
```

The two Hijri calendars happen to agree on this date; they need not agree on other dates. All four views read the same underlying day and time.

## Safe construction

`fromGregorian()`, `fromJalali()`, `fromHijri()` and `fromHijriCivil()` validate the date and time. Their `tryFrom…()` equivalents return `null` for invalid components (including an out-of-range Umm al-Qura date). `isValid…($year, $month, $day)` checks the date only. Time defaults to midnight; a timezone label defaults to `null`.

For an existing `DateTimeInterface` (including Carbon), use `CivilDateTime::fromDateTime($dt)`. For Unix seconds, use `fromTimestamp($timestamp, 'Asia/Tehran')`. For the current date use `today('Asia/Tehran')`; `now()`, `yesterday()` and `tomorrow()` are also available. Omitting their timezone uses and stores PHP's default timezone. These clock-dependent calls are not deterministic examples.

Continue with [concepts](concepts.md), [parsing](parsing.md) and [arithmetic](arithmetic.md).
