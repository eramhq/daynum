# Daynum

کتابخانه تغییرناپذیر تاریخ برای PHP 8.1 و بالاتر، با تقویم میلادی، شمسی، قمری ام‌القری و قمری محاسباتی. برای اجرا به پکیج دیگری در Composer یا `ext-intl` نیاز ندارد.

## نصب

```sh
composer require eram/daynum:1.0.0-beta.4
```

این راهنماها همراه نسخه آزمایشی **v1.0.0-beta.4** به تاریخ ۸ اکتبر ۲۰۲۶ هستند. [وضعیت انتشار](docs/fa/overview.md#وضعیت-انتشار) و [changelog](CHANGELOG.md) را ببینید.

## شروع سریع

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

تبدیل تقویم، ساعت محلی و برچسب اختیاری منطقه زمانی را حفظ می‌کند. محاسبات `CivilDateTime` برمی‌گردانند؛ برای نمایش دوباره view را انتخاب کنید. برای زمان سپری‌شده و تبدیل منطقه زمانی از timestamp یا PHP استفاده کنید.

## مستندات

- [راهنمای فارسی](docs/fa/overview.md) · [English guides](docs/en/overview.md)
- [شروع استفاده](docs/fa/getting-started.md) · [مثال‌های کاربردی](docs/fa/cookbook.md) · [مرجع API](docs/fa/api-reference.md)
- [مهاجرت از morilog/jalali](docs/fa/migration-from-morilog-jalali.md)
- [مشارکت](CONTRIBUTING.md) · [نگهداری مستندات](docs/README.md) · [امنیت](SECURITY.md)

## مجوز

[MIT](LICENSE). منابع اصلی در [الگوریتم‌ها و منابع](docs/fa/algorithms-and-attribution.md) آمده‌اند.
