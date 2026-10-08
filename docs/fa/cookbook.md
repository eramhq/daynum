---
title: "مثال‌های کاربردی"
description: "استفاده از Daynum در فرم ورودی، بازه ماهانه و انتخاب تقویم جایگزین."
---
# مثال‌های کاربردی

## مثال‌ها

هر مثال به راهنمای اصلی موضوع وصل است تا تعریف API تکرار نشود. مثال‌های مستقل خروجی دقیق دارند. قطعه کد Laravel به برنامه میزبان نیاز دارد و فقط از نظر syntax بررسی می‌شود.

## تبدیل میلادی به شمسی و برگشت

تاریخ را یک بار بسازید و view مورد نیاز را انتخاب کنید. [اولین مثال](getting-started.md#اولین-مثال) هر چهار تقویم را نشان می‌دهد. برای برگشت، از `dateTime()->gregorian()` استفاده کنید و متن نمایشی را دوباره parse نکنید.

## خواندن امن ورودی با tryParseExact

تقویم ورودی و قالب را مشخص کنید و برای نام‌ها، locale را هم بدهید. [راهنمای parsing](parsing.md) نشان می‌دهد اسفند ۳۰ نامعتبر چگونه null می‌دهد. ورودی اصلی را برای فرم نگه دارید و به جای نمایش مستقیم پیام exception، پیام اعتبارسنجی قابل فهم نشان دهید.

## اضافه کردن ماه در انتهای ماه

برای تکرار ماهانه مشخص کنید منظورتان «همان شماره روز، با محدود شدن به انتهای ماه» است یا «آخرین روز هر ماه». اضافه کردن پشت سر هم یک ماه به تاریخ محدودشده، روز را جابه‌جا می‌کند: ۳۱ ژانویه، ۲۸ فوریه، ۲۸ مارس. هر رخداد را با `addMonths($index)` از تاریخ اولیه بسازید، یا در هر ماه مقصد `endOfMonth()` را صدا بزنید. [مثال محاسبات](arithmetic.md#محدود-شدن-روز-به-انتهای-ماه) را ببینید.

## جست‌وجوی یک ماه شمسی

بازه `[start, until)` را با مرزهای نیمه‌شب بسازید؛ ابتدا شامل بازه است و انتها نیست. برای پایگاه داده مبتنی بر timestamp از `$start->toTimestamp()` و `$until->toTimestamp()` استفاده کنید. اگر داده محلی ذخیره می‌کنید، روز و ساعت را با سیاست یکسان منطقه زمانی مقایسه کنید. این مثال فقط مقدارها را محاسبه می‌کند و چیزی در پایگاه داده نمی‌نویسد.

```php
<?php
require 'vendor/autoload.php';

use Eram\Daynum\CivilDateTime;

$d = CivilDateTime::fromJalali(1405, 1, 19, 14, 30, 0, 'Asia/Tehran');
$start = $d->jalali()->startOfMonth()->startOfDay();
$until = $start->jalali()->addMonths(1);
echo $start->gregorian()->format('c'), "\n";
echo $until->gregorian()->format('c'), "\n";
var_export($d->greaterThanOrEqual($start) && $d->lessThan($until));
echo "\n";
```

```text
2026-03-21T00:00:00+03:30
2026-04-21T00:00:00+03:30
true
```

## مدیریت مرز ام‌القری

برای یک تاریخ میلادی مشخص، روز را حفظ کنید و نمایش قمری پشتیبانی‌شده را انتخاب کنید. نام تقویم را هم بنویسید تا تغییر مدل روشن باشد:

```php
<?php
require 'vendor/autoload.php';

use Eram\Daynum\CivilDateTime;

$d = CivilDateTime::fromGregorian(1800, 1, 1);
$view = $d->hijri();
if (!$view->isInSupportedRange()) {
    $view = $d->hijriCivil();
}
if (!$view->isInSupportedRange()) {
    throw new RuntimeException('No supported Hijri view');
}
echo $view->calendar()->name(), ': ', $view->format('Y/m/d'), "\n";
```

```text
hijri-civil: 1214/08/04
```

این روش، اجزای تاریخ ام‌القری واردشده توسط کاربر را به‌عنوان اجزای قمری محاسباتی تفسیر نمی‌کند.

## اضافه کردن ساعت واقعی با timestamp

برای یک ساعت واقعی از `CivilDateTime::fromTimestamp($d->toTimestamp() + 3600, $zone)` استفاده کنید. [مثال DST](timezones.md#محاسبه-زمان-سپریشده) نشان می‌دهد چرا `addHours(1)` ممکن است ساعت محلی دیگری بدهد.

## تبدیل بین مناطق زمانی

[تفاوت تبدیل و تغییر برچسب](timezones.md#تبدیل-بین-مناطق-زمانی) را ببینید. اگر لازم است رخداد دقیق یک ساعت تکراری حفظ شود، timestamp را نگه دارید.

## ذخیره و بازیابی CivilDateTime با JSON

از [مثال ذخیره‌سازی](serialization.md#قرارداد-رفتوبرگشت-json) استفاده کنید. تبدیل ORM باید ساختار خوانده‌شده را قبل از `fromArray()` بررسی کند و هر سه فیلد را حفظ کند. متن نمایشی تاریخ فارسی نباید به‌عنوان timestamp ذخیره شود.

## استفاده در درخواست و پاسخ Laravel

Daynum وابستگی فریم‌ورکی ندارد. در برنامه‌ای که Laravel را از قبل نصب کرده، اول نوع ورودی و بعد اعتبار تاریخ تقویمی را بررسی کنید:

```php
use Eram\Daynum\Calendar\Jalali\JalaliView;

// Context: a Laravel request handler; Laravel is installed by the application.
$input = $request->validate(['date' => ['required', 'string']]);
$d = JalaliView::tryParseExact($input['date'], 'Y/m/d', 'Asia/Tehran');
if ($d === null) {
    throw \Illuminate\Validation\ValidationException::withMessages([
        'date' => 'Invalid Jalali date (Y/m/d).',
    ]);
}
return response()->json($d);
```

منطقه زمانی مشخص‌شده یک تصمیم برنامه است. برای داده صرفا تاریخی که قرار نیست لحظه‌ای را نشان دهد، می‌توانید آن را null بگذارید. پاسخ JSON نمایش مقدار محلی است، نه تنظیمات زبان. پیام اعتبارسنجی را با زبان برنامه هماهنگ کنید.
