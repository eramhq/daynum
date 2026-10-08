---
title: "تقویم قمری ام‌القری"
description: "استفاده از جدول ام‌القری و مدیریت روشن تاریخ‌های خارج از محدوده."
---
# تقویم قمری ام‌القری

## ساخت تاریخ

`CivilDateTime::fromHijri()` و `hijri()` تقویم ام‌القری را انتخاب می‌کنند، نه قمری محاسباتی. کلاس خواندن ورودی `Eram\Daynum\Calendar\Hijri\HijriUmmAlQuraView` است. شناسه تقویم `hijri-umalqura`، گروه نام‌های زبان `hijri` و قالب پیش‌فرض `Y/m/d` است.

## جدول همراه کتابخانه

[جدول](../../../src/Calendar/Hijri/Table.php) از ICU 78.2 ساخته شده و سال‌های 1300 تا 1600 قمری را با احتساب هر دو مرز پوشش می‌دهد. طول ماه‌ها به‌صورت مقدارهای ۲۹ یا ۳۰ روزه ذخیره شده است. سالی که مجموعا ۳۵۵ روز دارد کبیسه در نظر گرفته می‌شود؛ الگوی کبیسه قمری محاسباتی را برای آن فرض نکنید. هنگام اجرا نه درخواست شبکه لازم است و نه `ext-intl`.

## چرا خارج از محدوده خطا می‌دهد

ساخت تاریخ خارج از جدول یا خواندن JDN خارج از محدوده، `UmmAlQuraOutOfRangeException` می‌دهد. `tryFromHijri()` مقدار null و `isValidHijri()` مقدار false برمی‌گرداند؛ این نتیجه‌ها ممکن است به دلیل اجزای نامعتبر تاریخ هم باشند، نه فقط محدوده. parsing خطای محدوده را به `ParseException` تبدیل می‌کند و `tryParseExact()` مقدار null می‌دهد.

روش برخورد با این حالت را روشن انتخاب کنید. برای مقدار تاریخی که از قبل دارید، اگر JDN در محدوده قمری محاسباتی است از `hijriCivil()` استفاده کنید و نوع تقویم را در خروجی بنویسید. برای اجزای تاریخ قمری که کاربر وارد کرده، عوض کردن متد ساخت یعنی تغییر تفسیر تقویم؛ فقط وقتی سیاست ورودی برنامه اجازه می‌دهد این کار را انجام دهید. تاریخ نامعتبر ام‌القری را با امتحان کردن تقویم دیگر بی‌سروصدا «اصلاح» نکنید. [مثال کاربردی](../cookbook.md#مدیریت-مرز-امالقری) را ببینید.

## عبور از مرز در محاسبات

اضافه کردن روز، JDN را بدون مراجعه به جدول تغییر می‌دهد، ولی خواندن نتیجه ممکن است خطا بدهد. محاسبه ماه و سال به جدول مقصد نیاز دارد و می‌تواند همان لحظه خطا بدهد:

```php
<?php
require 'vendor/autoload.php';

use Eram\Daynum\CivilDateTime;
use Eram\Daynum\Exception\UmmAlQuraOutOfRangeException;

$d = CivilDateTime::fromHijri(1600, 12, 29);
$next = $d->hijri()->addDays(100);
var_export($next->hijri()->isInSupportedRange());
echo "\n";
try {
    $next->hijri()->format('Y/m/d');
} catch (UmmAlQuraOutOfRangeException) {
    echo "Outside Umm al-Qura\n";
}
try {
    $d->hijri()->addYears(1);
} catch (UmmAlQuraOutOfRangeException) {
    echo "Year arithmetic needs table data\n";
}
```

```text
false
Outside Umm al-Qura
Year arithmetic needs table data
```

`isInSupportedRange()` بدون استخراج اجزای تاریخ، محدوده view را بررسی می‌کند. `supportedRange()` روی تقویم مرزهای JDN را با احتساب ابتدا و انتها می‌دهد. محاسبه شماره هفته نزدیک این مرزها می‌تواند `WeekAtBoundaryException` بدهد.

این جدول یک مدل مشخص تقویم است و اعلام محلی رویت ماه را پیش‌بینی نمی‌کند. تقویم مورد نیاز را با توجه به کاربران برنامه انتخاب کنید. [قمری محاسباتی](hijri-civil.md) مدل دیگری است، نه گزینه‌ای دقیق‌تر یا جایگزینی بدون محدودیت.
