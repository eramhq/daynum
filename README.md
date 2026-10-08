# Daynum

Immutable multi-calendar dates for PHP 8.1+: Gregorian, Jalali, Hijri Umm al-Qura and civil Hijri. No Composer runtime dependencies or runtime `ext-intl` requirement.

## Install

```sh
composer require eram/daynum:1.0.0-beta.4
```

These guides accompany **v1.0.0-beta.4**, a prerelease dated October 8, 2026. See [release status](docs/en/overview.md#release-status) and the [changelog](CHANGELOG.md).

## Quick start

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

Calendar conversion preserves wall-clock time and the optional timezone label. Arithmetic returns `CivilDateTime`; select a view again to format. Use timestamps or native PHP for elapsed time and timezone conversion.

## Documentation

- [English guides](docs/en/overview.md) · [راهنمای فارسی](docs/fa/overview.md)
- [Getting started](docs/en/getting-started.md) · [Recipes](docs/en/cookbook.md) · [API reference](docs/en/api-reference.md)
- [Migrating from morilog/jalali](docs/en/migration-from-morilog-jalali.md)
- [Contributing](CONTRIBUTING.md) · [Documentation maintenance](docs/README.md) · [Security](SECURITY.md)

## License

[MIT](LICENSE). See [algorithm attribution](docs/en/algorithms-and-attribution.md) for upstream sources.
