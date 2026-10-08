---
title: "مرجع API"
description: "پیدا کردن متدهای عمومی، مقدار پیش‌فرض آرگومان‌ها و نوع خروجی."
---
# مرجع API

جدول‌ها متدهای مرتبط را کنار هم نشان می‌دهند. تعریف کامل در [CivilDateTime](../../src/CivilDateTime.php)، [CalendarView](../../src/CalendarView.php) و [AbstractCalendarView](../../src/Calendar/AbstractCalendarView.php) است. مگر جایی که جداگانه گفته شده، namespace نام‌ها `Eram\Daynum` است. نوع خطاها در [راهنمای خطا](exceptions.md) آمده است.

## CivilDateTime

| API | آرگومان و خروجی |
|---|---|
| `__construct` | `int $jdn, int $secondsOfDay = 0, ?string $tzLabel = null` |
| `fromGregorian`, `fromJalali`, `fromHijri`, `fromHijriCivil` | `int $year, int $month, int $day, int $hour = 0, int $minute = 0, int $second = 0, ?string $tzLabel = null` → `CivilDateTime` |
| `tryFromGregorian`, `tryFromJalali`, `tryFromHijri`, `tryFromHijriCivil` | همان آرگومان‌ها → `?CivilDateTime` |
| `isValidGregorian`, `isValidJalali`, `isValidHijri`, `isValidHijriCivil` | `int $year, int $month, int $day` → `bool`؛ فقط تاریخ |
| `fromDateTime` | `DateTimeInterface $dt` → `CivilDateTime` |
| `fromTimestamp` | `int $timestamp, string $tzLabel = 'UTC'` → `CivilDateTime` |
| `now`, `today`, `tomorrow`, `yesterday` | `?string $tzLabel = null` → `CivilDateTime`؛ null یعنی استفاده و ذخیره پیش‌فرض PHP |
| `fromArray` | `array $data` → `CivilDateTime`؛ معکوس `jsonSerialize()` |
| `gregorian`, `jalali`, `hijri`, `hijriCivil` | بدون آرگومان → view همان تقویم |
| `jsonSerialize` | بدون آرگومان → آرایه شامل `jdn`، `secondsOfDay` و `tzLabel` |
| `toDateTimeImmutable`, `toTimestamp` | بدون آرگومان → `DateTimeImmutable` / `int` |
| `withJdn`, `withTzLabel` | `int $jdn` / `?string $tzLabel` → `CivilDateTime` |
| `withTime` | `int $hour, int $minute, int $second` → `CivilDateTime` |
| `addSeconds`, `subSeconds`, `addMinutes`, `subMinutes`, `addHours`, `subHours`, `addDays`, `subDays`, `addWeeks`, `subWeeks` | مقدار صحیح اجباری → `CivilDateTime`؛ جابه‌جایی ساعت محلی |
| `startOfDay`, `endOfDay` | بدون آرگومان → `CivilDateTime` |
| `equals`, `lessThan`, `greaterThan`, `lessThanOrEqual`, `greaterThanOrEqual`, `isSameDay` | `CivilDateTime $other` → `bool` |
| `diffInDays`, `diffInSeconds`, `diffInMinutes`, `diffInHours` | `CivilDateTime $other` → `int` علامت‌دار |
| استاتیک `compare` | `CivilDateTime $a, CivilDateTime $b` → `int` |
| استاتیک `min`, `max` | `CivilDateTime $first, CivilDateTime ...$rest` → `CivilDateTime` |
| `between` | `CivilDateTime $a, CivilDateTime $b, bool $inclusive = true` → `bool` |

`jdn`، `secondsOfDay` و `tzLabel` پراپرتی‌های عمومی readonly هستند. همه مقایسه‌های مقدار اصلی برچسب را نادیده می‌گیرند. [مفاهیم](concepts.md) و [منطقه زمانی](timezones.md) را ببینید.

## Viewهای تقویم

کلاس‌ها: `Calendar\Gregorian\GregorianView`، `Calendar\Jalali\JalaliView`، `Calendar\Hijri\HijriUmmAlQuraView` و `Calendar\Hijri\HijriCivilView`.

| API | آرگومان و خروجی |
|---|---|
| استاتیک `of` | `CivilDateTime $dateTime, ?string $locale = null, string $digitScript = 'latn'` → همان نوع view؛ locale برابر null یعنی en |
| استاتیک `parseExact`, `tryParseExact` | `string $text, string $format, ?string $tzLabel = null, ?string $locale = null` → `CivilDateTime` / `?CivilDateTime` |
| `dateTime`, `calendar` | بدون آرگومان → `CivilDateTime` / `Calendar` |
| `year`, `month`, `day`, `hour`, `minute`, `second` | بدون آرگومان → `int` |
| `dayOfWeek`, `dayOfWeekIso`, `dayOfYear`, `weekOfYear`, `weekBasedYear`, `daysInMonth`, `daysInYear`, `quarter` | بدون آرگومان → `int` |
| `isLeapYear`, `isWeekend`, `isWeekday`, `isInSupportedRange` | بدون آرگومان → `bool` |
| `weekDay` | بدون آرگومان → `WeekDay` |
| `format`, `__toString` | `string $pattern` / بدون آرگومان → `string` |
| `withLocale`, `withDigits` | `string $locale` / `string $script` → همان نوع view |
| `toArray` | بدون آرگومان → اجزای تقویم با کلیدهای نام‌دار؛ [ذخیره‌سازی](serialization.md) را ببینید |
| `addDays`, `subDays`, `addMonths`, `subMonths`, `addYears`, `subYears` | مقدار صحیح اجباری → `CivilDateTime` |
| `with` | اعداد صحیح nullable به نام `year`، `month`، `day`، `hour`، `minute` و `second`، هر کدام با پیش‌فرض null → `CivilDateTime` |
| `startOfMonth`, `endOfMonth`, `startOfYear`, `endOfYear`, `startOfQuarter`, `endOfQuarter` | بدون آرگومان → `CivilDateTime`؛ ساعت حفظ می‌شود |
| `startOfWeek`, `endOfWeek` | `WeekDay\|int\|null $weekStart = null` → `CivilDateTime`؛ null یعنی استفاده از locale |
| `diffInMonths`, `diffInYears` | `CivilDateTime $other` → `int` علامت‌دار |
| `diffForHumans`, `ago` | `CivilDateTime $other` / بدون آرگومان → `string` |
| فقط شمسی: `season`, `seasonName` | بدون آرگومان → `Season` / `string` |

## Calendar

هر چهار پیاده‌سازی متد استاتیک `instance()` دارند. [Calendar](../../src/Calendar.php) اجزای تاریخ را بدون ساعت و منطقه زمانی به JDN تبدیل می‌کند:

| متد | خروجی |
|---|---|
| `toJdn(int $year, int $month, int $day)` | `int`؛ تاریخ را بررسی می‌کند |
| `fromJdn(int $jdn)` | `[year, month, day]` |
| `isLeapYear(int $year)`, `supportsYear(int $year)` | `bool` |
| `daysInMonth(int $year, int $month)`, `monthsInYear(int $year)` | `int` |
| `dayOfYear(int $year, int $month, int $day)` | `int` از یک؛ ورودی را از قبل بررسی کنید |
| `name()`, `localeFamily()` | `string` |
| `supportedRange()` | `[minJdn, maxJdn]` با احتساب ابتدا و انتها |

برای ساخت تاریخ معتبر از factoryها استفاده کنید. تبدیل معکوس سطح پایین همه سال‌های خارج از محدوده را به یک شکل رد نمی‌کند. [محدوده تقویم‌ها](overview.md#انتخاب-تقویم) را ببینید.

## Enumها

`WeekDay`: Monday=1، Tuesday=2، Wednesday=3، Thursday=4، Friday=5، Saturday=6 و Sunday=7. `Season`: Spring=1، Summer=2، Autumn=3 و Winter=4. هر دو enum با مقدار صحیح هستند.

## LocaleRegistry و LocaleData

در namespace به نام `Eram\Daynum\Locale`، متدهای استاتیک `LocaleRegistry::get(string $tag): LocaleData`، `register(string $tag, LocaleData $locale): void`، `has(string $tag): bool`، `tags(): array` و `normalize(string $tag): string` زبان‌های مشترک فرایند را مدیریت می‌کنند. `normalize()` فقط متن را یکدست می‌کند؛ `get()` و `register()` تگ را اعتبارسنجی می‌کنند.

رابط `LocaleData` این متدها را می‌خواهد: `tag()`، `monthName()` و `monthNameShort()` با گروه تقویم و ماه، `weekdayName()` و `weekdayNameShort()` با یکشنبه=0، `meridiem(bool $isPm, bool $uppercase)`، `ordinalSuffix(int $day)`، `firstDayOfWeek(): WeekDay`، `weekendDays(): array`، `relativeTime(int $value, string $unit, bool $future)`، `relativeTimeNow()` و `seasonName(Season $season)`. متدهای نام و عبارت، رشته برمی‌گردانند. [رابط](../../src/Locale/LocaleData.php) و [مثال زبان سفارشی](localization.md#زبان-سفارشی) را ببینید.

## DigitTransliterator

کلاس `Eram\Daynum\Formatter\DigitTransliterator` ثابت‌های `LATN = 'latn'`، `PERSIAN = 'persian'` و `ARAB = 'arab'` و متدهای استاتیک `toLatin(string $text): string`، `toScript(string $text, string $script): string` و `isSupported(string $script): bool` را دارد. کاراکترهای غیرعددی تغییر نمی‌کنند. هم `withDigits()` روی view و هم `toScript()` نام ناشناخته شکل ارقام را با `InvalidArgumentException` خود Daynum رد می‌کنند. `toScript()` فقط ارقام ASCII را تبدیل می‌کند؛ برای تبدیل بین ارقام فارسی و عربی، اول `toLatin()` را صدا بزنید.
