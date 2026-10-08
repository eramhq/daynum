---
title: "زبان و ارقام"
description: "انتخاب مستقل زبان نام‌ها، قوانین هفته، زمان نسبی و شکل ارقام."
---
# زبان و ارقام

## انتخاب زبان

زبان پیش‌فرض همه viewها، حتی شمسی و قمری، `en` است. `withLocale()` نام‌ها، نشانه قبل و بعد از ظهر، عبارت‌های زمان نسبی، شروع هفته و تعطیلی آخر هفته را تغییر می‌دهد. تقویم و شکل ارقام را عوض نمی‌کند. برای ارقام، جداگانه از `withDigits('latn')`، `withDigits('persian')` یا `withDigits('arab')` استفاده کنید؛ فقط همین سه نام پذیرفته می‌شوند.

```php
<?php
require 'vendor/autoload.php';

use Eram\Daynum\CivilDateTime;
use Eram\Daynum\Locale\LocaleRegistry;

$d = CivilDateTime::fromJalali(1405, 1, 19);
echo $d->jalali()->withLocale('fa')->format('j F Y'), "\n";
echo $d->jalali()->withLocale('fa-AF')->withDigits('persian')->format('j F Y'), "\n";
echo $d->subDays(3)->jalali()->withLocale('fa')->withDigits('persian')->diffForHumans($d), "\n";
echo LocaleRegistry::get('fa_IR')->tag(), "\n";
```

```text
19 فروردین 1405
۱۹ حمل ۱۴۰۵
۳ روز پیش
fa
```

## تنظیمات هر زبان

| تگ | زبان | شروع هفته | آخر هفته |
|---|---|---|---|
| `en` | انگلیسی | دوشنبه | شنبه و یکشنبه |
| `fa` | فارسی | شنبه | جمعه |
| `fa-AF` | دری | شنبه | پنجشنبه و جمعه |
| `ar` | عربی | یکشنبه | جمعه و شنبه |
| `ps` | پشتو | شنبه | پنجشنبه و جمعه |
| `ur` | اردو | یکشنبه | شنبه و یکشنبه |
| `tr` | ترکی | دوشنبه | شنبه و یکشنبه |

این‌ها تنظیمات زبان در کتابخانه‌اند و جای تقویم تعطیلات یا روزهای کاری را نمی‌گیرند. `LocaleRegistry` بزرگی حروف و تفاوت `_` و `-` را نادیده می‌گیرد و تگ عمومی‌تر را هم امتحان می‌کند: `fa-IR` به `fa` می‌رسد، ولی `fa-AF` داده مستقل دارد. تگ ناشناخته یا بدقالب خطا می‌دهد و خودکار به انگلیسی تبدیل نمی‌شود. برای بررسی تگ‌ها از `has()`، `tags()` و `normalize()` استفاده کنید.

## محدودیت زبان عربی برای تقویم شمسی

locale عربی نام ماه‌های شمسی را ندارد. نمایش `F` و `M` خطای `InvalidArgumentException` می‌دهد و خواندن این نام‌ها با `ParseException` شکست می‌خورد. قالب عددی و نام روزهای هفته همچنان کار می‌کنند. برای نام فارسی ماه‌ها، `fa` را انتخاب کنید.

## زمان نسبی

`diffForHumans($other)` مقدار `$other` را مرجع می‌گیرد: اگر تاریخ شما قبل از آن باشد، عبارت گذشته برمی‌گرداند. بزرگ‌ترین واحد کامل را از بین سال، ماه، هفته، روز، ساعت، دقیقه و ثانیه انتخاب می‌کند. سال و ماه بر اساس تقویم view محاسبه می‌شوند و ساعت روز هم بررسی می‌شود. برای دو مقدار برابر، واژه معادل «اکنون» در زبان انتخابی می‌آید. `ago()` با زمان فعلی در منطقه زمانی ذخیره‌شده یا پیش‌فرض PHP مقایسه می‌کند. این عبارت‌ها بر پایه ساعت محلی‌اند، نه زمان واقعی سپری‌شده. وقتی محاسبه به ماه و سال نیاز دارد، هر دو تاریخ را در محدوده تقویم نگه دارید.

## زبان سفارشی

ثبت locale را یک بار هنگام راه‌اندازی برنامه انجام دهید. registry در کل فرایند مشترک است و می‌تواند localeهای داخلی را جایگزین کند. viewهای قبلی شیء locale خود را نگه می‌دارند. می‌توانید از یک locale داخلی ارث ببرید یا همه متدهای [LocaleData](../../src/Locale/LocaleData.php) را پیاده‌سازی کنید. مثال تغییر شروع هفته:

```php
<?php
require 'vendor/autoload.php';

use Eram\Daynum\CivilDateTime;
use Eram\Daynum\Locale\EnglishLocale;
use Eram\Daynum\Locale\LocaleRegistry;
use Eram\Daynum\WeekDay;

LocaleRegistry::register('en-team', new class extends EnglishLocale {
    public function firstDayOfWeek(): WeekDay
    {
        return WeekDay::Sunday;
    }
});
echo CivilDateTime::fromGregorian(2026, 4, 8)->gregorian()
    ->withLocale('en-team')->startOfWeek()->gregorian()->format('Y-m-d'), "\n";
```

```text
2026-04-05
```

جدول‌های زبان، املای منبع را حفظ می‌کنند و بعضی نام‌ها نشانه‌های ترکیبی دارند. متن فارسی راهنما بدون این نشانه‌ها نوشته می‌شود، ولی خروجی دقیق کتابخانه باید دست‌نخورده بماند. [DigitTransliterator](api-reference.md#digittransliterator) ارقام رشته‌های دلخواه را هم تبدیل می‌کند. توکن‌های منطقه زمانی و قالب‌های ماشینی از تبدیل ارقام جدا هستند؛ [راهنمای نمایش](formatting.md) را ببینید.
