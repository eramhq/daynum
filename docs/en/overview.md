---
title: "Daynum overview"
description: "Choose a calendar and find the guide for your task."
---
# Daynum overview

Daynum is an immutable PHP library for converting, displaying and calculating civil dates in Gregorian, Jalali and two Hijri calendars. It requires PHP 8.1+ and has no Composer runtime dependencies; `ext-intl` is not needed at runtime.

## Release status

These guides accompany [v1.0.0-beta.4](https://github.com/eramhq/daynum/releases/tag/v1.0.0-beta.4), dated October 8, 2026. It is a prerelease, not a stable 1.0 release. This version includes the bilingual guides, stricter repeated AM/PM and offset parsing, and improved name normalization. The [parsing guide](parsing.md#parsing-changes-in-beta4) identifies behavior added in beta.4; beta.3 does not include those changes. See the [release changelog](../../CHANGELOG.md#100-beta4--2026-10-08).

## Choose a task

- [Install and run your first example](getting-started.md).
- [Understand values, views and conversion](concepts.md).
- [Display localized dates](formatting.md) or [read user input](parsing.md).
- [Add months, compare dates and find boundaries](arithmetic.md).
- [Convert timezones and calculate elapsed time](timezones.md).
- [Save dates](serialization.md), [handle errors](exceptions.md), or follow a [recipe](cookbook.md).
- [Migrate from morilog/jalali](migration-from-morilog-jalali.md), consult the [API reference](api-reference.md), or review [algorithms and limitations](algorithms-and-attribution.md).

## Choose a calendar

| Calendar | Entry points | Supported construction years |
|---|---|---|
| [Gregorian](calendars/gregorian.md) | `fromGregorian()` / `gregorian()` | -9999–9999, including year 0 |
| [Jalali](calendars/jalali.md) | `fromJalali()` / `jalali()` | 1–3177 |
| [Hijri Umm al-Qura](calendars/hijri-umm-al-qura.md) | `fromHijri()` / `hijri()` | AH 1300–1600 |
| [Civil Hijri](calendars/hijri-civil.md) | `fromHijriCivil()` / `hijriCivil()` | AH 1–9666 |

A `CivilDateTime` stores a day, wall-clock time and optional timezone label. Calendar conversion keeps the time and label. It does not convert an instant between timezones. Use PHP's `DateTimeImmutable` or timestamps for that work; see [timezones](timezones.md).
