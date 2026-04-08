# Daynum

**Immutable, zero-runtime-dependency, multi-calendar PHP library.**

Daynum is a clean, single-package replacement for the 3–4 libraries PHP
developers currently glue together to get Gregorian + Jalali (Shamsi) + Hijri
support. It targets PHP 8.1+, requires no `ext-intl` at runtime, pulls no
transitive dependencies, and is differentially tested against ICU on ~220,000
dates per calendar per CI build.

v1 (M1) ships **Gregorian** and **Jalali**. Hijri (Umm al-Qura + civil) lands
in M2. Hebrew, Buddhist, Japanese and friends follow in post-v1 milestones,
each with its own ICU-oracle fixture set.

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
| **Correctness strategy** | Differential testing against ICU on 220,000+ dates per calendar, pre-committed as gzipped JSONL so the conformance suite runs without `ext-intl` |

## Install

```bash
composer require daynum/daynum
```

## Quick start

```php
use Daynum\Instant;

// ─── Construction ────────────────────────────────────────
$d = Instant::fromGregorian(2026, 4, 8);
$d = Instant::fromGregorian(2026, 4, 8, 14, 30, 0, 'Asia/Tehran');
$d = Instant::fromJalali(1405, 1, 19);
$d = Instant::fromDateTime(new DateTimeImmutable('2026-04-08 14:30'));
$d = Instant::today();

// ─── Views ───────────────────────────────────────────────
$d->gregorian()->year();   // 2026
$d->jalali()->year();      // 1405
$d->jalali()->month();     // 1   (1-indexed, never 0)
$d->jalali()->day();       // 19
$d->jalali()->dayOfWeek(); // 3   (Wednesday, Sun=0)
$d->jalali()->isLeapYear(); // false

// ─── Formatting ──────────────────────────────────────────
$d->gregorian()->format('Y-m-d');                                      // "2026-04-08"
$d->jalali()->format('Y/m/d');                                         // "1405/01/19"
$d->jalali()->format('l j F Y');                                       // "Wednesday 19 Farvardin 1405"
$d->jalali()->withLocale('fa')->format('l j F Y');                     // "چهارشنبه 19 فروردین 1405"
$d->jalali()->withLocale('fa')->withDigits('persian')->format('Y/m/d'); // "۱۴۰۵/۰۱/۱۹"
$d->jalali()->format('Y/m/d H:i T');                                   // "1405/01/19 14:30 Asia/Tehran"

// ─── Arithmetic (immutable; returns new Instant) ────────
$d->jalali()->addDays(7);
$d->jalali()->subMonths(1);
$d->jalali()->addYears(1);
$d->jalali()->startOfMonth();
$d->jalali()->endOfMonth();

// ─── Comparison ──────────────────────────────────────────
$a->equals($b);
$a->lessThan($b);
$a->diffInDays($b);        // signed integer
$a->jalali()->diffInMonths($b); // calendar-aware

// ─── Escape hatch to native PHP ─────────────────────────
$d->toDateTimeImmutable();  // hand off for real timezone math
```

## Opt-in global helpers

If you're migrating from `morilog/jalali` and miss the `jdate()` shorthand,
add this to your own application's `composer.json`:

```json
{
    "autoload": {
        "files": ["vendor/daynum/daynum/src/helpers.php"]
    }
}
```

Then:

```php
$d = jdate(1405, 1, 19);        // Instant::fromJalali
$d = gdate(2026, 4, 8);         // Instant::fromGregorian
// hdate() is reserved for M2 Hijri support.
```

Each helper is wrapped in `function_exists`, so it never silently clobbers a
name your application already defines.

## Jalali and ICU: a note on correctness

Daynum's Jalali calendar is the 33-year Birashk cycle ported from
`jalaali-js`, the algorithm behind `morilog/jalali` and `date-fns-jalali`.
This is a deliberate ecosystem choice — it's what every migrating PHP and JS
developer already tests against.

Birashk is NOT identical to ICU's `persian` calendar (which uses Borkowski's
arithmetic). Across the full 1700–2300 Gregorian fixture range the two
algorithms agree on **99.5%** of days. The remaining 0.5% form four contiguous
windows where Birashk and ICU assign the leap day to adjacent years:

| Nowruz in ICU | Nowruz in Birashk | Gregorian window    |
|---------------|-------------------|---------------------|
| 1078 AP       | (neither leap)    | 1700-01-01..1700-03-19 |
| 1177 AP       | 1176 AP           | 1797-03-21..1798       |
| 1503 AP       | 1502 AP           | 2123-03-21..2124       |
| 1602 AP       | 1601 AP           | 2222-03-21..2223       |

Within these windows Daynum is exactly one day behind ICU. For the ~300 years
between 1800 and 2122 — the practical modern range — the two algorithms agree
on every single day.

The `IcuConformanceTest` documents these windows explicitly. New divergences
outside them are treated as regressions and fail the build.

See `src/Calendar/Jalali/JalaliCalendar.php` for the full explanation.

## Testing

```bash
vendor/bin/phpunit                         # everything: ~148 tests, ~195k assertions
vendor/bin/phpunit --testsuite=unit         # pure unit tests
vendor/bin/phpunit --testsuite=edgecase     # boundary & leap-year hand-checks
vendor/bin/phpunit --testsuite=property     # randomized round-trip & ordering invariants
vendor/bin/phpunit --testsuite=conformance  # 220k-row ICU differential test per calendar
```

**The conformance suite needs no `ext-intl`.** The fixtures are committed as
gzipped JSONL under `tests/fixtures/` and regenerated only by the
`tools/generate-fixtures-*` scripts — see `tests/fixtures/README.md` for the
refresh procedure.

## Project layout

```
src/
├── Instant.php                     # immutable core type
├── Calendar.php                    # Calendar interface
├── CalendarView.php                # CalendarView interface
├── helpers.php                     # opt-in jdate/gdate/hdate globals
├── Exception/
│   ├── DaynumException.php         # marker interface
│   ├── InvalidDateException.php
│   └── UmmAlQuraOutOfRangeException.php  # (reserved for M2)
├── Formatter/
│   ├── DateTokenFormatter.php      # PHP date()-token engine
│   ├── FormatContext.php
│   └── DigitTransliterator.php     # latn ↔ persian (U+06F0) ↔ arab (U+0660)
├── Locale/
│   ├── LocaleData.php
│   ├── LocaleRegistry.php
│   ├── EnglishLocale.php
│   └── PersianLocale.php
└── Calendar/
    ├── AbstractCalendarView.php    # shared arithmetic + formatting
    ├── Gregorian/
    │   ├── GregorianCalendar.php   # Fliegel–Van Flandern proleptic
    │   └── GregorianView.php
    └── Jalali/
        ├── JalaliCalendar.php      # 33-year Birashk (jalaali-js port)
        └── JalaliView.php

tests/
├── Unit/                 # component tests
├── EdgeCase/             # hand-enumerated boundary cases
├── Conformance/          # ICU differential tests over committed fixtures
├── Property/             # randomized invariant tests
└── fixtures/             # pre-generated oracle data (~3 MB gzipped)

tools/
├── generate-fixtures-php.php       # oracle: PHP's ICU
├── generate-fixtures-node.mjs      # oracle: Node's ICU
├── generate-format-tokens.php      # golden format-token strings
└── verify-oracles-agree.php        # diffs the two oracles byte-for-byte
```

## Ported algorithms and attribution

| Component | Ported from | License |
|---|---|---|
| `GregorianCalendar` | Fliegel–Van Flandern algorithm, cross-checked against Reingold–Dershowitz *Calendrical Calculations* (4th ed., 2018) | public reference |
| `JalaliCalendar`    | `jalaali-js` — https://github.com/jalaali/jalaali-js (also the basis of `morilog/jalali` and `date-fns-jalali`) | MIT |

Attribution headers in the ported files name the exact upstream source.

## What's NOT shipped in v1

* Hijri Umm al-Qura + Hijri civil — **M2** (exception class `UmmAlQuraOutOfRangeException` already ships for forward compat)
* Observational Hijri (`islamic`), `islamic-rgsa`
* Hebrew, Buddhist, Japanese, Indian, Coptic, Ethiopic — post-v1 milestones, each with its own ICU oracle
* Timezone arithmetic — delegate to native `DateTimeImmutable` via `toDateTimeImmutable()`
* Sub-second precision, leap seconds, Julian/Gregorian cutover
* A framework bridge — a separate `daynum/laravel` package can ship post-v1 if demand exists

## License

MIT. See `LICENSE`.
