---
title: "Frequently asked questions"
description: "Resolve common surprises about dates, digits, parsing and timezones."
---
# Frequently asked questions

## Why are Jalali dates English by default?

Calendar and locale are independent. Use `withLocale('fa')` for names and `withDigits('persian')` for digits. Each newly selected view starts with `en`/`latn`; see [localization](localization.md).

## Why can't I format the result of addMonths directly?

It returns `CivilDateTime`. Select `jalali()` (or another view) again before `format()`. See [return types](concepts.md#immutability-and-return-types).

## Why does endOfMonth keep the hour?

Calendar boundaries choose a day. Use `endOfMonth()->endOfDay()` for 23:59:59, or a half-open range for database queries. See [arithmetic](arithmetic.md).

## Does changing the timezone label convert the time?

No. It changes the interpretation of the same wall-clock fields. For the same instant in another zone, convert through timestamps or native PHP. [Timezones](timezones.md) has executable examples.

## Why does an invalid date return null in one API and throw in another?

`tryFrom…()` and `tryParseExact()` are nullable alternatives to throwing factories/parsers. They are not catch-all wrappers for configuration errors. See [errors](exceptions.md).

## Can I parse names or relative phrases?

Month and weekday names are supported with an explicit parse locale. Relative phrases such as “next Monday” are not. See [parsing](parsing.md), including the changes introduced in beta.4.

## Is civil Hijri an unlimited fallback?

No. It supports construction in AH 1–9666 and uses a different calendar model from Umm al-Qura. Select and label that model explicitly; see [calendar choice](overview.md#choose-a-calendar).

## Does Daynum replace Carbon or morilog/jalali everywhere?

No. morilog v3 is also immutable; Carbon and native PHP have useful timezone and application APIs. Choose by task and test your call sites. See the sourced [migration guide](migration-from-morilog-jalali.md).
