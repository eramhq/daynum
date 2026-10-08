---
title: "تقویم میلادی"
description: "استفاده از تقویم میلادی تعمیم‌یافته، سال صفر و محدوده سال‌ها."
---
# تقویم میلادی

## ساخت تاریخ

از `CivilDateTime::fromGregorian()` و `gregorian()` استفاده کنید. کلاس خواندن ورودی `Eram\Daynum\Calendar\Gregorian\GregorianView` است. شناسه تقویم و گروه نام‌های زبان هر دو `gregorian` هستند. قالب پیش‌فرض رشته `Y-m-d` است.

## تقویم میلادی تعمیم‌یافته

Daynum قانون کبیسه میلادی را برای همه سال‌های پشتیبانی‌شده، حتی پیش از رواج تاریخی آن، اعمال می‌کند. تغییر تقویم ژولیوسی به میلادی و روزهای حذف‌شده در کشورهای مختلف را مدل نمی‌کند. شماره‌گذاری نجومی شامل سال صفر، معادل ۱ پیش از میلاد، است؛ سال -1 معادل ۲ پیش از میلاد است.

## قانون سال کبیسه

سال بخش‌پذیر بر 4 کبیسه است، مگر اینکه بر 100 بخش‌پذیر باشد ولی بر 400 نباشد.

```php
<?php
require 'vendor/autoload.php';

use Eram\Daynum\CivilDateTime;

var_export(CivilDateTime::isValidGregorian(1900, 2, 29));
echo "\n";
var_export(CivilDateTime::isValidGregorian(2000, 2, 29));
echo "\n";
echo CivilDateTime::fromGregorian(0, 1, 1)->gregorian()->format('Y-m-d'), "\n";
```

```text
false
true
0000-01-01
```

## محدوده

`GregorianCalendar::MIN_YEAR` برابر -9999 و `MAX_YEAR` برابر 9999 است و هر دو در ساخت تاریخ پذیرفته می‌شوند. `supportedRange()` مرزهای JDN را با احتساب ابتدا و انتها می‌دهد. تبدیل معکوس سطح پایین و محاسبه روی JDN می‌توانند مقدار خارج از محدوده ساخت تولید کنند؛ به جای اینکه انتظار داشته باشید همه متدهای خواندن خطا بدهند، `isInSupportedRange()` را بررسی کنید. محدودیت عدد صحیح و timestamp در پلتفرم PHP هم برقرار است.

[نمایش تاریخ](../formatting.md)، [محاسبات](../arithmetic.md) و [الگوریتم‌ها](../algorithms-and-attribution.md) را بخوانید.
