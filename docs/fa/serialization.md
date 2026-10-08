---
title: "ذخیره و بازیابی تاریخ"
description: "ذخیره مقدار تاریخ و تفاوت آرایه اجزای تقویم با JSON."
---
# ذخیره و بازیابی تاریخ

## قرارداد رفت‌وبرگشت JSON

`CivilDateTime` رابط `JsonSerializable` را پیاده‌سازی می‌کند و JSON آن شامل `jdn`، `secondsOfDay` و `tzLabel` است. تقویم اولیه، زبان و شکل ارقام را نگه نمی‌دارد. اگر این موارد بخشی از داده برنامه شما هستند، جداگانه ذخیره‌شان کنید.

```php
<?php
require 'vendor/autoload.php';

use Eram\Daynum\CivilDateTime;

$d = CivilDateTime::fromGregorian(2026, 4, 8, 14, 30, 0, 'Asia/Tehran');
$json = json_encode($d, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
echo $json, "\n";
$copy = CivilDateTime::fromArray(json_decode($json, true, flags: JSON_THROW_ON_ERROR));
var_export($copy->jsonSerialize() === $d->jsonSerialize());
echo "\n";
echo $copy->jalali()->format('Y/m/d'), "\n";
```

```text
{"jdn":2461139,"secondsOfDay":52200,"tzLabel":"Asia/Tehran"}
true
1405/01/19
```

## ساخت دوباره مقدار

`fromArray()` به `jdn` از نوع عدد صحیح نیاز دارد. اگر `secondsOfDay` وجود نداشته باشد یا `null` باشد، صفر می‌شود؛ در غیر این صورت باید عدد صحیح از 0 تا 86399 باشد. `tzLabel` حذف‌شده یا `null`، همان null می‌ماند؛ مقدار دیگر باید رشته باشد. رشته عددی، عدد صحیح محسوب نمی‌شود. کلیدهای اضافه نادیده گرفته می‌شوند. نام منطقه زمانی و محدوده تقویم اینجا بررسی نمی‌شوند؛ آن‌ها را برای کاربرد خود بررسی کنید. ساختار نامعتبر، `InvalidArgumentException` خود Daynum و ثانیه نامعتبر، `InvalidDateException` می‌دهد. خواندن JSON هم `JsonException` و بررسی نوع و ساختار خودش را دارد.

برای بررسی همه فیلدهای ذخیره‌شده از `equals()` استفاده نکنید، چون برچسب منطقه زمانی را نادیده می‌گیرد. به همین دلیل مثال، آرایه‌های ذخیره‌سازی را مقایسه می‌کند.

## آرایه اجزای تقویم

`toArray()` روی view کلیدهای `year`، `month`، `day`، `hour`، `minute`، `second` و `tzLabel` را با اجزای همان تقویم برمی‌گرداند. این آرایه `jdn` ندارد و ورودی `CivilDateTime::fromArray()` نیست. برای ساخت دوباره آن، متد مناسب `fromGregorian`، `fromJalali`، `fromHijri` یا `fromHijriCivil` را انتخاب کنید و فیلدها را جداگانه به آن بدهید.

## ذخیره در پایگاه داده

برای برنامه‌های مبتنی بر تاریخ و ساعت محلی، سه ستون با نوع مشخص داشته باشید: JDN صحیح، ثانیه صحیح و برچسب رشته‌ای که می‌تواند null باشد؛ یا از ستون JSON استفاده کنید. درایور پایگاه داده ممکن است اعداد را به‌صورت رشته برگرداند؛ همان‌جا اعتبارسنجی و تبدیل نوع را انجام دهید. برای قاعده‌ای مثل «هر ماه شمسی»، نام تقویم را هم ذخیره کنید. برای لحظه واقعی رویداد، timestamp در UTC و در صورت نیاز اطلاعات منطقه زمانی اولیه را نگه دارید. رشته datetime ساده در SQL برچسب را از دست می‌دهد؛ اضافه کردن برچسب بعدا یک تفسیر جدید است، نه بازیابی لحظه اصلی. Daynum تبدیل ORM آماده ندارد. [مثال‌های کاربردی](cookbook.md) و [محدودیت‌های منطقه زمانی](timezones.md) را ببینید.
