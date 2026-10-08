---
title: "خطاها و مدیریت آن‌ها"
description: "مدیریت خطاهای تاریخ، ورودی، منطقه زمانی و محدوده در جای مناسب."
---
# خطاها و مدیریت آن‌ها

همه exceptionهای تعریف‌شده کتابخانه در `Eram\Daynum\Exception` هستند و رابط نشانه‌گذار `DaynumException` را پیاده‌سازی می‌کنند. برای رسیدگی مشخص، همان کلاس خطا را بگیرید. اگر همه خطاهای Daynum در مرز برنامه یک روش رسیدگی دارند، از این رابط استفاده کنید. `TypeError` در PHP، خطاهای JSON و exceptionهای پیاده‌سازی locale خودتان زیرمجموعه آن نیستند.

## مرجع خطاها

| خطا | علت معمول | روش برخورد |
|---|---|---|
| `InvalidDateException` | تاریخ، ساعت یا سال نامعتبر هنگام ساخت | ورودی را رد یا اصلاح کنید؛ برای خروجی nullable از `tryFrom…()` استفاده کنید |
| `ParseException` | ناسازگاری قالب، تاریخ خوانده‌شده، محدوده یا روز هفته | راهنمای ورودی نشان دهید؛ `tryParseExact()` مقدار null می‌دهد |
| `InvalidArgumentException` | زبان، شکل ارقام، شروع هفته یا ساختار آرایه نامعتبر | تنظیمات یا نگاشت ورودی را اصلاح کنید |
| `MissingTimezoneException` | timestamp یا توکن منطقه زمانی بدون برچسب | منطقه زمانی مورد نظر را مشخص کنید، نه یک پیش‌فرض تصادفی سرور |
| `InvalidTimezoneException` | تبدیل با PHP و منطقه زمانی ناشناخته | انتخاب منطقه زمانی برنامه را بررسی کنید |
| `UmmAlQuraOutOfRangeException` | ساخت، خواندن یا محاسبه ماه و سال خارج از جدول | تقویم دیگری را آگاهانه انتخاب کنید یا ورودی را رد کنید |
| `WeekAtBoundaryException` | محاسبه هفته به پنجشنبه یا سال خارج از محدوده نیاز دارد | نمایش شماره هفته را حذف کنید یا مرز را جداگانه مدیریت کنید |

## خواندن تاریخ نامعتبر

`parseExact()` خطاهای تقویم، از جمله محدوده ام‌القری، را به `ParseException` تبدیل می‌کند. `tryParseExact()` فقط همین کلاس را می‌گیرد؛ پس locale ناشناخته همچنان خطا می‌دهد:

```php
<?php
require 'vendor/autoload.php';

use Eram\Daynum\Calendar\Gregorian\GregorianView;
use Eram\Daynum\Exception\DaynumException;

try {
    GregorianView::tryParseExact('2026-04-08', 'Y-m-d', locale: 'xx');
} catch (DaynumException $e) {
    echo get_class($e), "\n";
}
```

```text
Eram\Daynum\Exception\InvalidArgumentException
```

## UmmAlQuraOutOfRangeException

نتیجه `isValidHijri() === false` ثابت نمی‌کند که فقط باید به قمری محاسباتی بروید؛ ماه و روز نامعتبر هم همین نتیجه را دارند. [مدیریت مشخص تقویم](calendars/hijri-umm-al-qura.md) را ببینید.

کلاس‌های `InvalidArgumentException`، `InvalidDateException` و `ParseException` کتابخانه از `\InvalidArgumentException` در PHP ارث می‌برند. دو کلاس آخر زیرکلاس `InvalidArgumentException` خود Daynum نیستند. رابط نشانه‌گذار یا کلاس‌های مشخص مورد نیاز را بگیرید. پیام دقیق خطا برای عیب‌یابی است و API کد خطای ساختاریافته محسوب نمی‌شود.
