# Daynum

**Immutable, zero-runtime-dependency, multi-calendar PHP library.**

Daynum is a clean, single-package replacement for the 3–4 libraries PHP developers currently glue together to get Gregorian + Jalali (Shamsi) + Hijri support. It targets PHP 8.1+, requires no `ext-intl` at runtime, pulls no transitive dependencies, and is differentially tested against ICU on ~220,000 dates per calendar per CI build.

v1 ships **Gregorian**, **Jalali**, and **Hijri** (Saudi Umm al-Qura + tabular civil), with **English**, **Persian**, and **Arabic** locales.

## What Daynum is (and isn't)

**Daynum IS:** multi-calendar date conversion + formatting, immutable arithmetic, locale-aware formatting with PHP `date()` tokens, a zero-dependency ICU-tested replacement for `morilog/jalali`.

**Daynum is NOT:** a timezone library (use `toDateTimeImmutable()` for DST math), a relative-date parser ("next Monday"), a Carbon replacement (Carbon covers Gregorian + timezones; Daynum covers multi-calendar + correctness), or a framework bridge.

> **Naming caveat: `Instant` is civil, not UTC.** Unlike `java.time.Instant`, Daynum's `Instant` is a calendar-neutral civil datetime: `(JDN, time-of-day, timezone label)`. Two instants with the same JDN and time but different tzLabels represent different physical moments. See [docs/en/concepts.md](docs/en/concepts.md#instant-is-civil-not-utc).

| Feature | Daynum | Carbon | morilog/jalali | ext-intl |
|---------|--------|--------|----------------|----------|
| Jalali | Birashk 33-year | No | Birashk (same) | Borkowski |
| Hijri UAQ | Bundled table | No | No | Runtime ICU |
| Hijri Civil | Yes | No | No | Yes |
| Runtime deps | Zero | symfony/* | nesbot/carbon | ext-intl |
| Immutable | Yes | Optional | No | N/A |
| Testing | ICU differential | Unit tests | Unit tests | IS the oracle |

## Install

```bash
composer require eramhq/daynum
```

## Quick start

```php
use Daynum\Instant;
use Daynum\Calendar\Jalali\JalaliView;

// Construction — one calendar to pick from, three calendars to read back
$d = Instant::fromGregorian(2026, 4, 8, 14, 30, 0, 'Asia/Tehran');

$d->gregorian()->format('Y-m-d');                   // "2026-04-08"
$d->jalali()->format('Y/m/d');                      // "1405/01/19"
$d->hijri()->format('j F Y');                       // "21 Shawwal 1447"

// Persian locale + Persian digits
$d->jalali()->withLocale('fa')->withDigits('persian')->format('l j F Y');
// "چهارشنبه ۱۹ فروردین ۱۴۰۵"

// Immutable arithmetic — returns Instant, re-enter a view to format
$next = $d->jalali()->addMonths(1);                 // Instant
$next->jalali()->format('Y/m/d');                   // "1405/02/19"

// Strict parsing — digits in any script are normalized
JalaliView::parseExact('۱۴۰۵/۰۱/۱۹', 'Y/m/d');     // Instant
JalaliView::tryParseExact('nope', 'Y/m/d');         // null

// JSON round-trip contract
json_encode($d);
// {"jdn":2461139,"secondsOfDay":52200,"tzLabel":"Asia/Tehran"}
Instant::fromArray(json_decode(json_encode($d), true))->equals($d);   // true

// Escape hatch to native PHP for real timezone math
$d->toDateTimeImmutable();
```

## Documentation

**Learn**
- [Getting Started](docs/en/getting-started.md) — install, first example, 5-minute tour
- [Concepts](docs/en/concepts.md) — civil vs. UTC, `Instant` vs. view, JDN, immutability
- [Cookbook](docs/en/cookbook.md) — 10+ task-indexed recipes
- [FAQ](docs/en/faq.md) — surprising-but-intentional design decisions

**Calendars**
- [Gregorian](docs/en/calendars/gregorian.md) · [Jalali](docs/en/calendars/jalali.md) · [Hijri (Umm al-Qura)](docs/en/calendars/hijri-umm-al-qura.md) · [Hijri (civil)](docs/en/calendars/hijri-civil.md)

**Reference**
- [API Reference](docs/en/api-reference.md) — every type and method
- [Formatting](docs/en/formatting.md) · [Parsing](docs/en/parsing.md) · [Arithmetic](docs/en/arithmetic.md)
- [Localization](docs/en/localization.md) · [Timezones](docs/en/timezones.md) · [Exceptions](docs/en/exceptions.md) · [Serialization](docs/en/serialization.md)

**Migration & attribution**
- [Migrating from morilog/jalali](docs/en/migration-from-morilog-jalali.md)
- [Algorithms and attribution](docs/en/algorithms-and-attribution.md)

## Contributing

Bug reports, docs fixes, new locales, and new calendar systems are welcome. See [CONTRIBUTING.md](CONTRIBUTING.md) for testing, fixture regeneration, and the "how to add a new calendar" checklist.

## Links

- [CHANGELOG.md](CHANGELOG.md) — release notes
- [LICENSE](LICENSE) — MIT
- [Issue tracker](https://github.com/eramhq/daynum/issues)

## License

MIT. See [LICENSE](LICENSE).
