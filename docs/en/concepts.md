# Concepts

Daynum keeps five ideas in play. Understanding them up front makes every other page obvious.

## Instant is civil, not UTC

> **The #1 footgun.** Read this first.

Daynum's `Instant` is **not** a UTC timeline instant (unlike `java.time.Instant`). It is a calendar-neutral **civil datetime**: the triple `(JDN, time-of-day, timezone label)`.

Two `Instant` objects with the same JDN and time but different `tzLabel` values represent **different physical moments**. Comparison methods (`equals`, `lessThan`, etc.) compare wall-clock readings, not physical instants.

```php
$a = Instant::fromGregorian(2026, 4, 8, 14, 30, 0, 'Asia/Tehran');
$b = Instant::fromGregorian(2026, 4, 8, 14, 30, 0, 'UTC');

$a->equals($b);    // true — same JDN, same seconds-of-day
// But they are 3.5 hours apart on the UTC timeline!
```

For timeline-order comparison across timezones, convert to `DateTimeImmutable` first via `$instant->toDateTimeImmutable()`. That is the escape hatch for all "real" timezone math — see [timezones.md](timezones.md).

## Julian Day Number (JDN) is the interlingua

Every calendar in Daynum converts to and from the Julian Day Number — the integer count of days since a fixed epoch (noon UT, 1 January 4713 BC Julian). This means converting between any two calendars is as trivial as composing two functions.

```php
$d = Instant::fromJalali(1405, 1, 19);
$d->gregorian()->format('Y-m-d');   // "2026-04-08"
$d->hijri()->format('j F Y');       // "21 Shawwal 1447"
```

You never touch JDNs directly in normal use — they live on the `Instant` as `$d->jdn`, but you read dates through calendar *views*.

## Instant vs. View

`Instant` is the immutable value. A **view** (`GregorianView`, `JalaliView`, `HijriUmmAlQuraView`, `HijriCivilView`) pairs that `Instant` with a specific calendar system and locale.

```php
$d = Instant::fromGregorian(2026, 4, 8);     // just an Instant

$d->gregorian()->year();                      // 2026 — entered a view
$d->jalali()->year();                         // 1405 — different view, same Instant
$d->jalali()->format('l j F Y');              // "Wednesday 19 Farvardin 1405"
```

**Arithmetic on a view returns an `Instant`, not another view.** To format the result, re-enter a view:

```php
$next = $d->jalali()->addMonths(1);   // Instant
$next->jalali()->format('Y/m/d');     // re-enter Jalali view to format
```

This shape is deliberate: the arithmetic is calendar-specific (month-clamping differs per calendar), but the result is calendar-neutral until you ask for a specific view again.

## Immutability

Every value type in Daynum is a `final` class with `readonly` properties. Methods that appear to mutate — `addDays`, `subMonths`, `withLocale`, `withDigits`, `withTzLabel` — always return a new instance.

```php
$a = Instant::fromJalali(1405, 1, 19);
$b = $a->jalali()->addDays(7);        // $a is unchanged
$a === $b;                             // false
```

You can safely share an `Instant` across threads, caches, or call sites without worrying about aliasing.

## Design decisions

| | |
|---|---|
| **License** | MIT |
| **PHP** | `>=8.1` — stays on the shared-hosting baseline |
| **Runtime deps** | Zero. `ext-intl` is only used by the fixture generators, never by library code |
| **Immutability** | Every value type is a `final` class with `readonly` properties |
| **API naming** | Carbon / morilog style (`addDays`, `subMonths`, `lessThan`, `format('Y/m/d')`) — the same idioms the migration audience already types by reflex |
| **Format tokens** | PHP `date()` syntax (`Y`, `m`, `d`, `F`, `l`, `H`, `i`, `s`, …), NOT ICU patterns |
| **Month indexing** | **1-based** (`January = 1`), explicitly rejecting ICU's 0-based trap |
| **Day of week** | `dayOfWeek()` is PHP's Sunday=0..Saturday=6; `dayOfWeekIso()` is ISO Monday=1..Sunday=7 |
| **Proleptic Gregorian** | Year 0 exists, negative years permitted, no Oct 1582 cutover |
| **Jalali algorithm** | 33-year Birashk cycle (ported from `jalaali-js`/`morilog`), supported range **1–3177 AP** |
| **Hijri algorithms** | Saudi Umm al-Qura (KACST table, bundled from ICU) + tabular `islamic-civil` (Reingold–Dershowitz year-16 leap variant). UAQ throws on out-of-range; civil works for AH 1–9666. |
| **Correctness strategy** | Differential testing against ICU on 220,000+ dates per calendar, pre-committed as gzipped JSONL so the conformance suite runs without `ext-intl` |

## Scope: what's not shipped in v1

The [README](../../README.md) covers the "Daynum is / isn't" framing. Beyond that headline list, the following are explicitly out of scope for v1:

- Observational Hijri (`islamic`), `islamic-tbla` (Thursday-epoch), `islamic-rgsa`
- Arabic Jalali output — ICU's Arabic transliterations of Persian month names are low quality, so Arabic + Jalali throws on `F`/`M` tokens. Use the Persian locale instead; it renders in the same Perso-Arabic script
- Hebrew, Buddhist, Japanese, Indian, Coptic, Ethiopic — post-v1 milestones, each with its own ICU oracle
- Sub-second precision, leap seconds, Julian/Gregorian cutover
- Relative date parsing ("next Monday", "+2 weeks")
- A framework bridge — a separate `daynum/laravel` package can ship post-v1 if demand exists

## See also

- [getting-started.md](getting-started.md) — install and run the first example
- [timezones.md](timezones.md) — the escape hatch and when to use it
- [arithmetic.md](arithmetic.md) — clamping, cross-calendar diffs, boundary behavior
