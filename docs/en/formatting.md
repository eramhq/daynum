---
title: "Formatting"
description: "Format calendar dates with supported PHP-style tokens."
---
# Formatting

## Format a view

Call `format($pattern)` on a calendar view. The formatter supports the tokens below, not every token of every PHP version. Unknown characters pass through; escape a token letter with a backslash for literal text. A cast to string uses `Y-m-d` for Gregorian and `Y/m/d` for the other views.

```php
<?php
require 'vendor/autoload.php';

use Eram\Daynum\CivilDateTime;

$v = CivilDateTime::fromGregorian(2026, 4, 8, 14, 30, 0, 'Asia/Tehran')
    ->jalali()->withLocale('fa')->withDigits('persian');
echo $v->format('Y/m/d H:i P'), "\n";
echo $v->format('c'), "\n";
echo $v->format('\\Y Y'), "\n";
```

```text
۱۴۰۵/۰۱/۱۹ ۱۴:۳۰ +03:30
2026-04-08T14:30:00+03:30
Y ۱۴۰۵
```

## Token reference

| Tokens | Meaning |
|---|---|
| `Y y` | Calendar year, at least four digits / last two digits |
| `m n F M` | Month padded / unpadded / full name / short name |
| `d j S` | Day padded / unpadded / locale ordinal suffix |
| `l D N w` | Full / short weekday name; Monday=1 / Sunday=0 numbering |
| `z` | Zero-based day of year (`dayOfYear()` is one-based) |
| `W o` | Week number / week-based year using the Thursday rule in this calendar |
| `t L` | Days in month / leap-year flag |
| `H G h g` | 24-hour padded / unpadded; 12-hour padded / unpadded |
| `i s a A` | Minute, second, locale AM/PM markers |
| `u v` | Always `000000` / `000` (no subsecond storage) |
| `e T` | Stored timezone label / PHP timezone abbreviation |
| `O P p Z I U` | Offset, offset with colon, offset or Z, offset seconds, DST flag, Unix seconds |
| `c r` | PHP Gregorian ISO 8601 / RFC 2822 output |

`e T O P p Z I U c r` require a non-null timezone label. Except for `e`, which prints the label directly, these use native PHP timezone resolution and may reject an invalid label. Their output bypasses digit conversion. `c` and `r` always use Gregorian dates even on a Jalali or Hijri view; other calendar tokens use the selected calendar. At DST gaps, native output may normalize the wall-clock reading; see [timezones](timezones.md).

`W` and `o` may throw at range boundaries. They do not adopt locale week starts. `S` is locale-dependent (empty in Persian and Arabic). Arabic locale has no Jalali month names: `F`/`M` throw there. Read [localization](localization.md) and [parsing](parsing.md) before assuming formatted strings can be parsed back.
