---
title: "منطقه زمانی"
description: "تفاوت برچسب منطقه زمانی، تبدیل یک لحظه و زمان سپری‌شده."
---
# منطقه زمانی

## نقش برچسب منطقه زمانی

برچسب مشخص می‌کند وقتی PHP به منطقه زمانی نیاز دارد، ساعت محلی را چطور تفسیر کند. `withTzLabel()` فقط این رشته را عوض می‌کند؛ نه ساعت را جابه‌جا می‌کند و نه لحظه قبلی را حفظ می‌کند. تبدیل تقویم هم ساعت محلی را دست‌نخورده نگه می‌دارد.

## تبدیل بین مناطق زمانی

برای تبدیل یک لحظه از timestamp استفاده کنید، مثل مثال زیر. راه دیگر این است که روی خروجی `toDateTimeImmutable()` متد `setTimezone(new DateTimeZone(...))` را صدا بزنید و سپس با `CivilDateTime::fromDateTime()` برگردید.

```php
<?php
require 'vendor/autoload.php';

use Eram\Daynum\CivilDateTime;

$d = CivilDateTime::fromGregorian(2026, 4, 8, 12, 0, 0, 'UTC');
$relabeled = $d->withTzLabel('Asia/Tehran');
$converted = CivilDateTime::fromTimestamp($d->toTimestamp(), 'Asia/Tehran');
echo $relabeled->gregorian()->format('H:i P'), "\n";
echo $converted->gregorian()->format('H:i P'), "\n";
var_export($d->equals($relabeled));
echo "\n";
echo $relabeled->toTimestamp() - $d->toTimestamp(), "\n";
```

```text
12:00 +03:30
15:30 +03:30
true
-12600
```

در مثال بالا، دو ساعت محلی برابر به لحظه‌های متفاوت اشاره می‌کنند. مقایسه مقدار اصلی برچسب را نادیده می‌گیرد، ولی مقایسه timestamp این تفاوت را نشان می‌دهد.

## محاسبه زمان سپری‌شده

محاسبات تقویمی برای برنامه‌ای بر اساس تاریخ و ساعت محلی مناسب‌اند. زمان واقعی سپری‌شده باید روی خط زمان محاسبه شود. این مثال تغییر ساعت بهاری نیویورک را نشان می‌دهد:

```php
<?php
require 'vendor/autoload.php';

use Eram\Daynum\CivilDateTime;

$d = CivilDateTime::fromGregorian(2026, 3, 8, 1, 30, 0, 'America/New_York');
$wall = $d->addHours(1);
$elapsed = CivilDateTime::fromTimestamp($d->toTimestamp() + 3600, 'America/New_York');
echo $wall->gregorian()->format('Y-m-d H:i'), "\n";
echo $elapsed->gregorian()->format('Y-m-d H:i'), "\n";
echo $elapsed->diffInHours($d), "\n";
echo $elapsed->toTimestamp() - $d->toTimestamp(), "\n";
```

```text
2026-03-08 02:30
2026-03-08 03:30
2
3600
```

نتیجه محلی `02:30` در آن روز وجود خارجی ندارد، ولی Daynum می‌تواند آن را ذخیره کند. تبدیل به PHP این فاصله را اصلاح می‌کند. برای تعداد دقیق ثانیه‌های سپری‌شده، عدد صحیح را به timestamp اضافه کنید؛ یک روز محلی لزوما ۲۴ ساعت واقعی نیست.

## استفاده از toDateTimeImmutable

این تبدیل، اجزای تاریخ میلادی و برچسب را با پایگاه داده مناطق زمانی PHP تفسیر می‌کند. اگر برچسب نباشد، این متد از منطقه زمانی پیش‌فرض PHP استفاده می‌کند؛ اما `toTimestamp()` و توکن‌های نمایش منطقه زمانی به برچسب مشخص نیاز دارند و در نبود آن `MissingTimezoneException` می‌دهند. `now()` و `today()` در صورت حذف منطقه زمانی، مقدار پیش‌فرض را استفاده و ذخیره می‌کنند؛ متدهای معمول ساخت تاریخ آن را `null` می‌گذارند.

متدهای ساخت تاریخ و `withTzLabel()` می‌توانند برچسب نامعتبر را ذخیره کنند؛ تبدیل با PHP آن را با `InvalidTimezoneException` رد می‌کند. اختلاف ثابت مثل `+03:30` تغییر DST ندارد. منطقه‌ای مثل `America/New_York` قواعد وابسته به تاریخ دارد.

ساعت‌های حذف‌شده در تغییر بهاری به جلو منتقل می‌شوند. در حالت آزمایش‌شده ساعت تکراری پاییز، تبدیل به اولین رخداد می‌رسد؛ مقدار محلی فیلد «fold» برای تشخیص دو رخداد ندارد. پس تبدیل timestamp به مقدار محلی و برگشت، ممکن است رخداد دوم ساعت تکراری را از دست بدهد. `fromDateTime()` میکروثانیه را هم حذف می‌کند. اگر این تفاوت‌ها مهم‌اند، timestamp یا شیء اصلی PHP را نگه دارید. نتیجه تبدیل منطقه زمانی به پایگاه داده نصب‌شده PHP بستگی دارد. [تست‌های مرزی](../../tests/Unit/CivilDateTimeBoundaryTest.php) را ببینید.
