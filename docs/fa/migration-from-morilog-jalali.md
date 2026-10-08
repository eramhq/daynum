---
title: "مهاجرت از morilog/jalali"
description: "جایگزینی کارهای Jalalian با Daynum و بررسی تفاوت رفتارها."
---
# مهاجرت از morilog/jalali

Daynum جایگزین مستقیم و بدون تغییر کد نیست. **morilog/jalali نسخه 3 تغییرناپذیری را پشتیبانی می‌کند**؛ این موضوع در [مستندات نسخه 3](https://github.com/morilog/jalali/blob/v3.4.2/README.md#version-3-features) آمده است. دلیل مهاجرت باید API و تقویم‌های مورد نیاز برنامه باشد، نه ادعای برتری بی‌پایه در تغییرناپذیری، سرعت یا دقت.

## قبل و بعد

ستون چپ مربوط به morilog نسخه 3 است. پیش از تغییر کد، نسخه نصب‌شده خود را بررسی کنید. مثال‌های Daynum اینجا برای beta.4 هستند. [راهنمای parsing](parsing.md) تغییرات نسبت به beta.3 را مشخص می‌کند.

| کار در morilog v3 | معادل در Daynum |
|---|---|
| `new Jalalian($y, $m, $d)` | `CivilDateTime::fromJalali($y, $m, $d)` |
| `Jalalian::now()` / `jdate()` | `CivilDateTime::now('Asia/Tehran')->jalali()` |
| `Jalalian::forge($timestamp)` | `CivilDateTime::fromTimestamp($timestamp, 'Asia/Tehran')->jalali()` |
| `Jalalian::fromDateTime($dt)` / `fromCarbon($carbon)` | `CivilDateTime::fromDateTime($dt)->jalali()` برای `DateTimeInterface` |
| `Jalalian::fromFormat('Y/m/d', $text)` | `JalaliView::parseExact($text, 'Y/m/d')`؛ ترتیب آرگومان‌ها برعکس است |
| `CalendarUtils::checkDate($y, $m, $d)` | `CivilDateTime::isValidJalali($y, $m, $d)` |
| `getYear()` / `getMonth()` / `getDay()` | `year()` / `month()` / `day()` روی view |
| `getMonthDays()` | `daysInMonth()` روی view |
| `getTimestamp()` | `toTimestamp()` روی مقدار اصلی با برچسب منطقه زمانی |
| `toCarbon()` | در صورت نصب Carbon، `Carbon\CarbonImmutable::instance($d->toDateTimeImmutable())` |

برای تبدیل به آرایه عددی سه‌عضوی، از `fromJdn($d->jdn)` روی تقویم استفاده کنید یا `year()`، `month()` و `day()` را از view بگیرید. `toArray()` آرایه‌ای با کلیدهای نام‌دار و فیلدهای ساعت است، نه آرایه تبدیل سه‌عضوی morilog.

## Daynum تابع jdate ندارد

فراخوانی helperهای سراسری را صریح بازنویسی کنید. Daynum در beta.2 helperهای قبلی را حذف کرد و بدون alias، نام `Instant` را به `CivilDateTime` و `instant()` را به `dateTime()` تغییر داد. [changelog](../../CHANGELOG.md) را ببینید.

## تفاوت‌های مهم

نمایش پیش‌فرض Daynum انگلیسی با ارقام لاتین است. زبان و ارقام را هر دو تنظیم کنید. `dayOfWeek()` در Daynum یکشنبه را صفر می‌گیرد، ولی `getDayOfWeek()` در morilog شنبه را صفر می‌گیرد. محاسبات تقویمی `CivilDateTime` برمی‌گردانند و به انتخاب دوباره view نیاز دارند:

```php
<?php
require 'vendor/autoload.php';

use Eram\Daynum\CivilDateTime;
use Eram\Daynum\Calendar\Jalali\JalaliView;

$d = JalaliView::parseExact('1405/01/19', 'Y/m/d', 'Asia/Tehran');
$next = $d->jalali()->addMonths(1);
echo $next->jalali()->withLocale('fa')->withDigits('persian')->format('l j F Y'), "\n";
echo $d->jalali()->format('Y/m/d'), "\n";
```

```text
شنبه ۱۹ اردیبهشت ۱۴۰۵
1405/01/19
```

Daynum از توکن‌های سبک PHP استفاده می‌کند، نه قالب strftime با علامت درصد: `%A` به `l`، `%d` به `d`، `%B` به `F` و `%Y` به `Y` تبدیل می‌شود. علامت‌های `%` را بدون بررسی نگه ندارید. parsing تاریخ نامعتبر را رد می‌کند و عبارت نسبی مثل «next Monday» را نمی‌پذیرد. در تست‌های مهاجرت برنامه، رفتار انتهای ماه، نام‌ها، شماره روز هفته، خطای محدوده و سیاست منطقه زمانی را بررسی کنید.

## چه زمانی Carbon یا PHP مناسب است

برای تبدیل منطقه زمانی از [DateTimeImmutable](https://www.php.net/manual/en/class.datetimeimmutable.php) استفاده کنید. [Carbon](https://github.com/CarbonPHP/carbon) API تاریخ PHP را با متدهای کمکی گسترش می‌دهد و در کنار `Carbon` تغییرپذیر، `CarbonImmutable` هم دارد. اگر برنامه به آن APIها وابسته است، نگهش دارید. برای نمایش تقویمی، شیء موجود را با `fromDateTime()` تبدیل کنید و [محدودیت دقت و ساعت تکراری](timezones.md) را در نظر بگیرید.

هنگام مهاجرت می‌توانید Daynum و morilog را کنار هم نصب کنید. فقط وقتی تست‌های برنامه قبول شدند و هیچ وابستگی یا helper باقی‌مانده‌ای به morilog نیاز نداشت، آن را حذف کنید. مثال‌های مستندات هیچ migration فریم‌ورکی یا حذف پکیجی اجرا نمی‌کنند.
