---
title: "خواندن ورودی تاریخ"
description: "خواندن تاریخ با قالب مشخص، رد تاریخ نامعتبر و مدیریت خطا."
---
# خواندن ورودی تاریخ

## `parseExact` و `tryParseExact`

کلاس view را بر اساس تقویم ورودی انتخاب کنید: `GregorianView`، `JalaliView`، `HijriUmmAlQuraView` یا `HijriCivilView`. متد `parseExact($text, $format, ?string $tzLabel = null, ?string $locale = null)` خروجی `CivilDateTime` دارد. `tryParseExact()` همین آرگومان‌ها را می‌گیرد و در صورت `ParseException` مقدار `null` برمی‌گرداند. تاریخ نامعتبر رد می‌شود و به ماه بعد منتقل نمی‌شود.

```php
<?php
require 'vendor/autoload.php';

use Eram\Daynum\Calendar\Jalali\JalaliView;
use Eram\Daynum\Exception\ParseException;

$d = JalaliView::parseExact('۱۹ فروردین ۱۴۰۵', 'j F Y', locale: 'fa');
echo $d->gregorian()->format('Y-m-d'), "\n";
var_export(JalaliView::tryParseExact('1404/12/30', 'Y/m/d'));
echo "\n";
try {
    JalaliView::parseExact('1404/12/30', 'Y/m/d');
} catch (ParseException $e) {
    echo get_class($e), "\n";
}
```

```text
2026-04-08
NULL
Eram\Daynum\Exception\ParseException
```

## توکن‌های قابل خواندن

| توکن‌ها | ورودی |
|---|---|
| `Y` | حداقل چهار رقم با علامت منفی اختیاری؛ قبل از توکن چسبیده، دقیقا چهار رقم |
| `m d H h i s` | دقیقا دو رقم |
| `n j G g` | یک یا دو رقم؛ قبل از توکن بعدی جداکننده بگذارید |
| `F M` | نام کامل یا کوتاه ماه در زبان ورودی |
| `l D` | نام کامل یا کوتاه روز هفته که باید با تاریخ سازگار باشد |
| `a A` | نشانه قبل یا بعد از ظهر در locale؛ همچنین `am`/`pm`، `ق.ظ`/`ب.ظ` و `ص`/`م` |
| `P p O` | برای P/p مقدار `+HH:MM` یا `Z`؛ برای O مقدار `+HHMM` |
| `c` | معادل `Y-m-d\TH:i:sP` در تقویم view ورودی |

قالب باید سال، ماه به‌صورت عدد یا نام، و روز را مشخص کند. اجزای ساعت که در قالب نیامده‌اند صفر می‌شوند. `h` و `g` به توکن AM/PM نیاز دارند؛ فیلدهای ساعت ۱۲ و ۲۴ ساعته را با هم ترکیب نکنید. توکن‌های مخصوص نمایش مثل `y`، `U`، `W`، `o`، `e` و `r` قابل خواندن نیستند و خطا می‌دهند. فاصله‌ها و جداکننده‌ها باید دقیقا مطابق قالب باشند و متن اضافی در انتها پذیرفته نمی‌شود. با بک‌اسلش می‌توانید حرف یک توکن را به‌صورت متن ثابت مشخص کنید.

## نام ماه و روز هفته

زبان ورودی به‌صورت پیش‌فرض `en` است و از view دیگری گرفته نمی‌شود. ارقام فارسی و عربی به لاتین تبدیل می‌شوند. برای نام‌ها، شکل کامل و کوتاه پذیرفته می‌شود؛ ی و ک عربی و فارسی یکسان در نظر گرفته می‌شوند، نیم‌فاصله و همزه ترکیبی نادیده گرفته می‌شوند و بزرگی و کوچکی حروف ASCII، Latin-1 و ترکی تفاوتی ندارد. این رفتار فقط برای تطبیق نام است و API عمومی نرمال‌سازی Unicode نیست. locale عربی نام ماه شمسی ندارد، ولی ورودی عددی همچنان قابل خواندن است.

## اختلاف ساعت

اختلاف ساعت باید در بازه `-12:00` تا `+14:00` باشد. مقدار خوانده‌شده از متن جای `$tzLabel` را می‌گیرد و یک اختلاف ثابت باقی می‌ماند؛ منطقه زمانی IANA با قوانین DST نیست. برچسبی که جداگانه می‌دهید، تا زمان نیاز به تبدیل با PHP اعتبارسنجی نمی‌شود.

خروجی `format('c')` همیشه میلادی است، اما خواندن `c` از تقویم کلاس view پیروی می‌کند. رشته‌های ISO را با `GregorianView::parseExact($text, 'c')` بخوانید، حتی اگر از `format('c')` روی view شمسی آمده باشند.

## حالت‌های خطا

تاریخ نامعتبر، خروج از محدوده، روز هفته ناسازگار و اشتباه قالب به `ParseException` تبدیل می‌شوند. locale ناشناخته `InvalidArgumentException` خود Daynum را ایجاد می‌کند و `tryParseExact()` آن را نمی‌گیرد. نوع اشتباه آرگومان PHP هم می‌تواند `TypeError` بدهد. [راهنمای خطاها](exceptions.md) را ببینید.

## تغییرات parsing در beta.4

رفتار زیر در beta.4 اضافه شده است. توکن‌های تکراری AM/PM یا اختلاف ساعت که با هم تناقض دارند اکنون رد می‌شوند؛ beta.3 آخرین مقدار را نگه می‌داشت. اختلاف‌های معادل مثل `+03:30` و `+0330` پذیرفته می‌شوند. تطبیق نام اکنون اعراب عربی را هم نادیده می‌گیرد، پس نام ماه اردو در مثال زیر بدون اعراب قابل خواندن است. تطبیق حروف ASCII هم دیگر به locale زبان C در فرایند وابسته نیست. خروجی نمایش نام‌ها تغییری نکرده است. [changelog](../../CHANGELOG.md#100-beta4--2026-10-08) را ببینید.

```php
<?php
require 'vendor/autoload.php';

use Eram\Daynum\Calendar\Gregorian\GregorianView;
use Eram\Daynum\Calendar\Hijri\HijriCivilView;

var_export(GregorianView::tryParseExact('2026-04-08 10:00 am pm', 'Y-m-d h:i a a'));
echo "\n";
var_export(GregorianView::tryParseExact('2026-04-08+03:30 +0400', 'Y-m-dP O'));
echo "\n";
echo HijriCivilView::parseExact('01 ربیع الاول 1447', 'd F Y', locale: 'ur')
    ->hijriCivil()->format('Y/m/d'), "\n";
```

```text
NULL
NULL
1447/03/01
```
