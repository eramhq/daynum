---
title: "API reference"
description: "Find public entry points, argument defaults and return types."
---
# API reference

The tables group related methods. Full declarations live in [CivilDateTime](../../src/CivilDateTime.php), [CalendarView](../../src/CalendarView.php) and [AbstractCalendarView](../../src/Calendar/AbstractCalendarView.php). Names use the `Eram\Daynum` namespace unless noted. See [errors](exceptions.md) for failure types.

## CivilDateTime

| API | Arguments / result |
|---|---|
| `__construct` | `int $jdn, int $secondsOfDay = 0, ?string $tzLabel = null` |
| `fromGregorian`, `fromJalali`, `fromHijri`, `fromHijriCivil` | `int $year, int $month, int $day, int $hour = 0, int $minute = 0, int $second = 0, ?string $tzLabel = null` → `CivilDateTime` |
| `tryFromGregorian`, `tryFromJalali`, `tryFromHijri`, `tryFromHijriCivil` | Same arguments → `?CivilDateTime` |
| `isValidGregorian`, `isValidJalali`, `isValidHijri`, `isValidHijriCivil` | `int $year, int $month, int $day` → `bool`; date only |
| `fromDateTime` | `DateTimeInterface $dt` → `CivilDateTime` |
| `fromTimestamp` | `int $timestamp, string $tzLabel = 'UTC'` → `CivilDateTime` |
| `now`, `today`, `tomorrow`, `yesterday` | `?string $tzLabel = null` → `CivilDateTime`; null uses/stores PHP default |
| `fromArray` | `array $data` → `CivilDateTime`; inverse of `jsonSerialize()` |
| `gregorian`, `jalali`, `hijri`, `hijriCivil` | No arguments → corresponding concrete view |
| `jsonSerialize` | No arguments → array with `jdn`, `secondsOfDay`, `tzLabel` |
| `toDateTimeImmutable`, `toTimestamp` | No arguments → `DateTimeImmutable` / `int` |
| `withJdn`, `withTzLabel` | `int $jdn` / `?string $tzLabel` → `CivilDateTime` |
| `withTime` | `int $hour, int $minute, int $second` → `CivilDateTime` |
| `addSeconds`, `subSeconds`, `addMinutes`, `subMinutes`, `addHours`, `subHours`, `addDays`, `subDays`, `addWeeks`, `subWeeks` | Required integer amount → `CivilDateTime`; wall-clock shifts |
| `startOfDay`, `endOfDay` | No arguments → `CivilDateTime` |
| `equals`, `lessThan`, `greaterThan`, `lessThanOrEqual`, `greaterThanOrEqual`, `isSameDay` | `CivilDateTime $other` → `bool` |
| `diffInDays`, `diffInSeconds`, `diffInMinutes`, `diffInHours` | `CivilDateTime $other` → signed `int` |
| static `compare` | `CivilDateTime $a, CivilDateTime $b` → `int` |
| static `min`, `max` | `CivilDateTime $first, CivilDateTime ...$rest` → `CivilDateTime` |
| `between` | `CivilDateTime $a, CivilDateTime $b, bool $inclusive = true` → `bool` |

`jdn`, `secondsOfDay`, `tzLabel` are public readonly properties. All core comparisons ignore labels. See [concepts](concepts.md) and [timezones](timezones.md).

## Calendar views

Concrete classes: `Calendar\Gregorian\GregorianView`, `Calendar\Jalali\JalaliView`, `Calendar\Hijri\HijriUmmAlQuraView`, `Calendar\Hijri\HijriCivilView`.

| API | Arguments / result |
|---|---|
| static `of` | `CivilDateTime $dateTime, ?string $locale = null, string $digitScript = 'latn'` → same view type; null locale means en |
| static `parseExact`, `tryParseExact` | `string $text, string $format, ?string $tzLabel = null, ?string $locale = null` → `CivilDateTime` / `?CivilDateTime` |
| `dateTime`, `calendar` | No arguments → `CivilDateTime` / `Calendar` |
| `year`, `month`, `day`, `hour`, `minute`, `second` | No arguments → `int` |
| `dayOfWeek`, `dayOfWeekIso`, `dayOfYear`, `weekOfYear`, `weekBasedYear`, `daysInMonth`, `daysInYear`, `quarter` | No arguments → `int` |
| `isLeapYear`, `isWeekend`, `isWeekday`, `isInSupportedRange` | No arguments → `bool` |
| `weekDay` | No arguments → `WeekDay` |
| `format`, `__toString` | `string $pattern` / no arguments → `string` |
| `withLocale`, `withDigits` | `string $locale` / `string $script` → same view type |
| `toArray` | No arguments → named calendar components; see [serialization](serialization.md) |
| `addDays`, `subDays`, `addMonths`, `subMonths`, `addYears`, `subYears` | Required integer amount → `CivilDateTime` |
| `with` | Nullable integer `year`, `month`, `day`, `hour`, `minute`, `second`, each default null → `CivilDateTime` |
| `startOfMonth`, `endOfMonth`, `startOfYear`, `endOfYear`, `startOfQuarter`, `endOfQuarter` | No arguments → `CivilDateTime`; time preserved |
| `startOfWeek`, `endOfWeek` | `WeekDay\|int\|null $weekStart = null` → `CivilDateTime`; null uses locale |
| `diffInMonths`, `diffInYears` | `CivilDateTime $other` → signed `int` |
| `diffForHumans`, `ago` | `CivilDateTime $other` / no arguments → `string` |
| Jalali only: `season`, `seasonName` | No arguments → `Season` / `string` |

## Calendar

All four implementations expose static `instance()`. [Calendar](../../src/Calendar.php) maps date components to JDN, without time or zone:

| Method | Result |
|---|---|
| `toJdn(int $year, int $month, int $day)` | `int`, validates date |
| `fromJdn(int $jdn)` | `[year, month, day]` |
| `isLeapYear(int $year)`, `supportsYear(int $year)` | `bool` |
| `daysInMonth(int $year, int $month)`, `monthsInYear(int $year)` | `int` |
| `dayOfYear(int $year, int $month, int $day)` | `int`, one-based; prevalidate input |
| `name()`, `localeFamily()` | `string` |
| `supportedRange()` | `[minJdn, maxJdn]`, inclusive |

Use the factories for validated construction. Low-level reverse conversion does not uniformly reject every unsupported year. See [calendar ranges](overview.md#choose-a-calendar).

## Enums

`WeekDay`: Monday=1, Tuesday=2, Wednesday=3, Thursday=4, Friday=5, Saturday=6, Sunday=7. `Season`: Spring=1, Summer=2, Autumn=3, Winter=4. Both are integer-backed enums.

## LocaleRegistry and LocaleData

Under `Eram\Daynum\Locale`, static `LocaleRegistry::get(string $tag): LocaleData`, `register(string $tag, LocaleData $locale): void`, `has(string $tag): bool`, `tags(): array` and `normalize(string $tag): string` manage process-wide locales. `normalize()` only normalizes text; `get()`/`register()` validate tags.

`LocaleData` requires `tag()`, `monthName()`/`monthNameShort()` (family and month), `weekdayName()`/`weekdayNameShort()` (Sunday=0), `meridiem(bool $isPm, bool $uppercase)`, `ordinalSuffix(int $day)`, `firstDayOfWeek(): WeekDay`, `weekendDays(): array`, `relativeTime(int $value, string $unit, bool $future)`, `relativeTimeNow()` and `seasonName(Season $season)`. Name/phrase methods return strings. See the [interface](../../src/Locale/LocaleData.php) and [custom locale example](localization.md#custom-locales).

## DigitTransliterator

`Eram\Daynum\Formatter\DigitTransliterator` has constants `LATN = 'latn'`, `PERSIAN = 'persian'`, `ARAB = 'arab'` and static `toLatin(string $text): string`, `toScript(string $text, string $script): string`, `isSupported(string $script): bool`. Non-digit characters remain unchanged. Both view `withDigits()` and `toScript()` reject an unrecognized script with Daynum’s `InvalidArgumentException`. `toScript()` maps ASCII digits only; call `toLatin()` first when converting between Persian and Arabic-Indic digits.
