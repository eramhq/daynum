# Daynum

**Immutable, zero-runtime-dependency, multi-calendar PHP library.**

Daynum is a clean, single-package replacement for the 3–4 libraries PHP
developers currently glue together to get Gregorian + Jalali (Shamsi) + Hijri
support. It targets PHP 8.1+, requires no `ext-intl` at runtime, pulls no
transitive dependencies, and is differentially tested against ICU on ~220,000
dates per calendar per CI build.

v1 ships **Gregorian**, **Jalali**, and **Hijri** (Saudi Umm al-Qura + tabular
civil). Hebrew, Buddhist, Japanese and friends follow in post-v1 milestones,
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
| **Hijri algorithms** | Saudi Umm al-Qura (KACST table, bundled from ICU) + tabular `islamic-civil` (Reingold–Dershowitz year-16 leap variant). UAQ throws on out-of-range; civil works for AH 1–9666. |
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
$d = Instant::fromHijri(1447, 10, 21);            // Saudi Umm al-Qura
$d = Instant::fromHijriCivil(1447, 10, 21);       // tabular, AH 1..9666
$d = Instant::fromDateTime(new DateTimeImmutable('2026-04-08 14:30'));
$d = Instant::today();

// ─── Views ───────────────────────────────────────────────
$d->gregorian()->year();    // 2026
$d->jalali()->year();       // 1405
$d->jalali()->month();      // 1   (1-indexed, never 0)
$d->jalali()->day();        // 19
$d->jalali()->dayOfWeek();  // 3   (Wednesday, Sun=0)
$d->jalali()->isLeapYear(); // false
$d->hijri()->year();        // 1447 — Saudi Umm al-Qura, throws if out of range
$d->hijriCivil()->year();   // 1447 — tabular fallback, always works

// ─── Formatting ──────────────────────────────────────────
$d->gregorian()->format('Y-m-d');                                      // "2026-04-08"
$d->jalali()->format('Y/m/d');                                         // "1405/01/19"
$d->jalali()->format('l j F Y');                                       // "Wednesday 19 Farvardin 1405"
$d->jalali()->withLocale('fa')->format('l j F Y');                     // "چهارشنبه 19 فروردین 1405"
$d->jalali()->withLocale('fa')->withDigits('persian')->format('Y/m/d'); // "۱۴۰۵/۰۱/۱۹"
$d->jalali()->format('Y/m/d H:i T');                                   // "1405/01/19 14:30 Asia/Tehran"
$d->hijri()->format('j F Y');                                          // "21 Shawwal 1447"
$d->hijri()->withLocale('fa')->format('j F Y');                        // "21 شوال 1447"
$d->hijri()->withLocale('fa')->withDigits('arab')->format('j F Y');    // "٢١ شوال ١٤٤٧"

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
$d = hdate(1447, 10, 21);       // Instant::fromHijri (Umm al-Qura)
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

## Hijri: two variants, both ICU-tested

Daynum ships two Hijri implementations side-by-side because the Persian-PHP
audience and the Saudi/Gulf audience expect different things.

### `Instant::fromHijri()` / `$d->hijri()` — Saudi Umm al-Qura (KACST)

The official Saudi calendar, with month lengths hand-curated per year by
KACST. This is what Persian websites display alongside Jalali, and what
Saudi/Gulf users expect to see when an app shows them an Islamic date.
Daynum bundles ICU's `islamic-umalqura` table verbatim
(`src/Calendar/Hijri/Table.php`, regenerated by
`tools/generate-uaq-table.php`) so no `ext-intl` is needed at runtime.

The bundled table covers a fixed range — currently **AH 1300 to AH 1600**
(roughly 1882 to 2174 CE), the exact window over which ICU exposes native
KACST data. Outside this window ICU silently falls back to the arithmetic
civil calendar, which is precisely the kind of quiet credibility hazard
Daynum exists to prevent. So Daynum **throws**:

```php
Instant::fromHijri(1200, 1, 1);
// → UmmAlQuraOutOfRangeException:
// "Hijri Umm al-Qura date 1200-01-01 is outside the supported range
//  (AH 1300 to AH 1600). Use fromHijriCivil() for dates outside this range."
```

### `Instant::fromHijriCivil()` / `$d->hijriCivil()` — tabular civil

The deterministic 30-year arithmetic Islamic calendar with the year-16
leap variant — identical to ICU's `islamic-civil`, Reingold–Dershowitz's
"Arithmetic Islamic Calendar", and Joda-Time. Pure formula, no table,
works for **AH 1 through AH 9666**.

Use this for historical and far-future dates where the Umm al-Qura table
doesn't reach. Across the entire 1700–2300 Gregorian fixture window
Daynum's civil implementation matches ICU's `islamic-civil` byte-for-byte
on all 219,510 days.

### Why not `islamic-tbla` or observational `islamic`?

`islamic-tbla` is the same calendar but with a Thursday epoch instead of
Friday — a rare variant Daynum does not ship in v1. Observational
`islamic` (astronomical new-moon visibility) is non-deterministic and
will never ship: pretending to compute it from a closed-form formula
would be a lie.

## Testing

```bash
vendor/bin/phpunit                         # everything: ~220 tests, ~240k assertions
vendor/bin/phpunit --testsuite=unit         # pure unit tests
vendor/bin/phpunit --testsuite=edgecase     # boundary & leap-year hand-checks
vendor/bin/phpunit --testsuite=property     # randomized round-trip & ordering invariants
vendor/bin/phpunit --testsuite=conformance  # ~750k-row ICU differential test across all four calendars
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
│   └── UmmAlQuraOutOfRangeException.php  # thrown by Hijri UAQ when out of bundled range
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
    ├── Jalali/
    │   ├── JalaliCalendar.php      # 33-year Birashk (jalaali-js port)
    │   └── JalaliView.php
    └── Hijri/
        ├── HijriCivilCalendar.php      # Reingold–Dershowitz year-16 leap variant
        ├── HijriCivilView.php
        ├── HijriUmmAlQuraCalendar.php  # KACST table consumer
        ├── HijriUmmAlQuraView.php
        └── Table.php                   # bundled UAQ data, generated

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
├── generate-uaq-table.php          # dump ICU's UAQ data into Hijri/Table.php
└── verify-oracles-agree.php        # diffs the two oracles byte-for-byte
```

## Ported algorithms and attribution

| Component | Ported from | License |
|---|---|---|
| `GregorianCalendar` | Fliegel–Van Flandern algorithm, cross-checked against Reingold–Dershowitz *Calendrical Calculations* (4th ed., 2018) | public reference |
| `JalaliCalendar`    | `jalaali-js` — https://github.com/jalaali/jalaali-js (also the basis of `morilog/jalali` and `date-fns-jalali`) | MIT |
| `HijriCivilCalendar` | Reingold–Dershowitz *Calendrical Calculations* "Arithmetic Islamic" chapter, cross-checked against ICU `IslamicCalendar.java` (CIVIL type) | public reference + Unicode ICU license |
| `Hijri/Table.php`    | Generated verbatim from ICU's bundled `islamic-umalqura` data (originally KACST). Cross-verified against Rob van Gent's academic table at webspace.science.uu.nl/~gent0113. | Unicode ICU license |

Attribution headers in the ported files name the exact upstream source.

## What's NOT shipped in v1

* Observational Hijri (`islamic`), `islamic-tbla` (Thursday-epoch), `islamic-rgsa`
* Arabic locale (`ar`) — natural fit for Hijri, kept separate so M2 stays focused; coming as a post-v1 milestone
* Hebrew, Buddhist, Japanese, Indian, Coptic, Ethiopic — post-v1 milestones, each with its own ICU oracle
* Timezone arithmetic — delegate to native `DateTimeImmutable` via `toDateTimeImmutable()`
* Sub-second precision, leap seconds, Julian/Gregorian cutover
* A framework bridge — a separate `daynum/laravel` package can ship post-v1 if demand exists

## License

MIT. See `LICENSE`.
