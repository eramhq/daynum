---
title: "شروع استفاده"
description: "نصب Daynum و اجرای یک مثال کامل برای تبدیل تقویم."
---
# شروع استفاده

## نصب

به PHP 8.1 یا بالاتر و Composer نیاز دارید. برای اجرای کتابخانه نه `ext-intl` لازم است و نه پکیج دیگری در Composer. ابزارهای توسعه از وابستگی‌های dev در [composer.json](../../composer.json) استفاده می‌کنند.

برای نصب نسخه آزمایشی فعلی، نسخه را مشخص کنید:

```sh
composer require eram/daynum:1.0.0-beta.4
```

اگر می‌خواهید نسخه‌های آزمایشی سازگار بعدی هم قابل نصب باشند، از `composer require 'eram/daynum:^1.0@beta'` استفاده کنید و نسخه نصب‌شده و changelog را بررسی کنید. هیچ‌کدام از این دستورها تغییرات منتشرنشده شاخه توسعه را نصب نمی‌کنند. [وضعیت انتشار](overview.md#وضعیت-انتشار) را ببینید.

## اولین مثال

این کد را کنار پوشه `vendor/` در فایل `example.php` ذخیره کنید و `php example.php` را اجرا کنید. همه مثال‌های کامل این راهنماها از ریشه پروژه اجرا می‌شوند و خروجی دقیق آن‌ها در بلوک `text` بعد از کد آمده است.

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

دو تقویم قمری در این تاریخ اتفاقا یک نتیجه دارند؛ در تاریخ‌های دیگر ممکن است متفاوت باشند. هر چهار view همان روز و ساعت ذخیره‌شده را می‌خوانند.

## ساخت تاریخ با بررسی اعتبار

متدهای `fromGregorian()`، `fromJalali()`، `fromHijri()` و `fromHijriCivil()` تاریخ و ساعت را بررسی می‌کنند. نسخه‌های `tryFrom…()` برای ورودی نامعتبر `null` برمی‌گردانند؛ تاریخ خارج از محدوده ام‌القری هم شامل این حالت است. متدهای `isValid…($year, $month, $day)` فقط اعتبار تاریخ را بررسی می‌کنند. ساعت پیش‌فرض نیمه‌شب و برچسب پیش‌فرض منطقه زمانی `null` است.

اگر از قبل یک `DateTimeInterface`، از جمله Carbon، دارید از `CivilDateTime::fromDateTime($dt)` استفاده کنید. برای ثانیه‌های Unix از `fromTimestamp($timestamp, 'Asia/Tehran')` و برای تاریخ امروز از `today('Asia/Tehran')` استفاده کنید. متدهای `now()`، `yesterday()` و `tomorrow()` هم در دسترس‌اند. اگر منطقه زمانی این متدها را مشخص نکنید، مقدار پیش‌فرض PHP را استفاده و ذخیره می‌کنند. خروجی این متدها به زمان اجرا بستگی دارد.

در ادامه [مفاهیم](concepts.md)، [خواندن ورودی](parsing.md) و [محاسبات تاریخ](arithmetic.md) را بخوانید.
