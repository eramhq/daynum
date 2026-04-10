# Daynum

**Immutable, zero-runtime-dependency, multi-calendar PHP library.**

Daynum is a clean, single-package replacement for the 3–4 libraries PHP
developers currently glue together to get Gregorian + Jalali (Shamsi) + Hijri
support. It targets PHP 8.1+, requires no `ext-intl` at runtime, pulls no
transitive dependencies, and is differentially tested against ICU on ~220,000
dates per calendar per CI build.

v1 ships **Gregorian**, **Jalali**, and **Hijri** (Saudi Umm al-Qura + tabular
civil), with **English**, **Persian**, and **Arabic** locales. Hebrew, Buddhist,
Japanese and friends follow in post-v1 milestones, each with its own ICU-oracle
fixture set.

See [`CHANGELOG.md`](CHANGELOG.md) for release notes.

## What Daynum is (and isn't)

**Daynum IS:**
- Multi-calendar date conversion + formatting (Gregorian, Jalali, Hijri)
- Immutable arithmetic (`addDays`, `addMonths`, `addYears`, `startOfMonth`, …)
- Locale-aware formatting with PHP `date()` tokens
- A zero-dependency, ICU-tested replacement for `morilog/jalali`

**Daynum is NOT:**
- Timezone arithmetic — use `toDateTimeImmutable()` for DST transitions, UTC offsets, etc.
- Relative date parsing — no "next Monday", "+2 weeks", or fuzzy input
- A Carbon replacement — Carbon covers Gregorian + timezone; Daynum covers multi-calendar + correctness
- A framework bridge — a separate `daynum/laravel` package may ship post-v1

| Feature | Daynum | Carbon | morilog/jalali | ext-intl |
|---------|--------|--------|----------------|----------|
| Jalali | Birashk 33-year | No | Birashk (same) | Borkowski |
| Hijri UAQ | Bundled table | No | No | Runtime ICU |
| Hijri Civil | Yes | No | No | Yes |
| Runtime deps | Zero | symfony/* | nesbot/carbon | ext-intl |
| Immutable | Yes | Optional | No | N/A |
| Testing | ICU differential | Unit tests | Unit tests | IS the oracle |

## Naming caveat: Instant is not a UTC instant

Daynum's `Instant` is **not** a UTC timeline instant (unlike `java.time.Instant`).
It is a calendar-neutral **civil datetime**: `(JDN, time-of-day, timezone label)`.
Two `Instant` objects with the same JDN and time but different `tzLabel` values
represent different physical moments. Comparison methods (`equals`, `lessThan`,
etc.) compare wall-clock readings, not physical instants.

For timeline-order comparison across timezones, convert to `DateTimeImmutable`
first via `$instant->toDateTimeImmutable()`.

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
composer require eramhq/daynum
```

## Quick start

```php
use Daynum\Instant;
use Daynum\Calendar\Gregorian\GregorianView;
use Daynum\Calendar\Jalali\JalaliView;
use Daynum\Calendar\Hijri\HijriUmmAlQuraView;

// ─── Construction ────────────────────────────────────────
$d = Instant::fromGregorian(2026, 4, 8);
$d = Instant::fromGregorian(2026, 4, 8, 14, 30, 0, 'Asia/Tehran');
$d = Instant::fromJalali(1405, 1, 19);
$d = Instant::fromHijri(1447, 10, 21);            // Saudi Umm al-Qura
$d = Instant::fromHijriCivil(1447, 10, 21);       // tabular, AH 1..9666
$d = Instant::fromDateTime(new DateTimeImmutable('2026-04-08 14:30'));
$d = Instant::now();                                   // current date + time, resolves timezone
$d = Instant::now('Asia/Tehran');                       // current date + time in Tehran
$d = Instant::today();                                  // today at 00:00:00
$d = Instant::tomorrow();                               // tomorrow at 00:00:00
$d = Instant::yesterday('Asia/Tehran');                 // yesterday at 00:00:00 in Tehran

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
$d->hijri()->withLocale('ar')->format('j F Y');                        // "21 شوال 1447"
$d->hijri()->withLocale('ar')->withDigits('arab')->format('j F Y');    // "٢١ شوال ١٤٤٧"

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

// ─── Parsing ────────────────────────────────────────────
$d = GregorianView::parseExact('2026-04-08', 'Y-m-d');
$d = JalaliView::parseExact('1405/01/19', 'Y/m/d');
$d = JalaliView::parseExact('۱۴۰۵/۰۱/۱۹', 'Y/m/d');  // Persian digits normalized
$d = HijriUmmAlQuraView::parseExact('1447/10/21', 'Y/m/d');
$d = GregorianView::parseExact('2026-04-08 02:30 PM', 'Y-m-d h:i A');

// ─── Safe parsing ───────────────────────────────────────
$d = GregorianView::tryParseExact('2026-04-10', 'Y-m-d');   // Instant or null
$d = JalaliView::tryParseExact('not-a-date', 'Y/m/d');      // null

// ─── Safe construction ──────────────────────────────────
$d = Instant::tryFromJalali(1405, 13, 1);    // null (invalid month)
$d = Instant::tryFromHijri(1200, 1, 1);      // null (out of UAQ table range)
Instant::isValidJalali(1405, 1, 19);         // true
Instant::isValidHijri(1447, 1, 31);          // false

// ─── Serialization ──────────────────────────────────────
json_encode($d);                    // {"jdn":2461139,"secondsOfDay":52200,"tzLabel":"Asia/Tehran"}
Instant::fromArray($jsonDecoded);   // reconstruct from JSON round-trip
$d->jalali()->toArray();            // ['year'=>1405,'month'=>1,'day'=>19,'hour'=>14,…]

// ─── Escape hatch to native PHP ─────────────────────────
$d->toDateTimeImmutable();  // hand off for real timezone math
```

## Format tokens

Daynum uses PHP `date()` syntax. All tokens work across all calendars.

| Token | Output | Example (Jalali) |
|-------|--------|------------------|
| `Y` | Full year (4+ digits) | `1405` |
| `y` | 2-digit year | `05` |
| `m` | Month, zero-padded | `01` |
| `n` | Month, no padding | `1` |
| `d` | Day, zero-padded | `19` |
| `j` | Day, no padding | `19` |
| `F` | Month name, long (locale) | `Farvardin` / `فروردین` |
| `M` | Month name, short (locale) | `Far` / `فروردین` |
| `l` | Weekday name, long (locale) | `Wednesday` / `چهارشنبه` |
| `D` | Weekday name, short (locale) | `Wed` / `چهارشنبه` |
| `z` | Day of year, 0-indexed | `18` |
| `W` | ISO week number, zero-padded | `16` |
| `o` | ISO week-based year | `1405` |
| `S` | Ordinal suffix (locale) | `th` |
| `G` | Hour 24h, no padding | `14` |
| `H` | Hour 24h, zero-padded | `14` |
| `g` | Hour 12h, no padding | `2` |
| `h` | Hour 12h, zero-padded | `02` |
| `i` | Minute, zero-padded | `30` |
| `s` | Second, zero-padded | `00` |
| `a` | am/pm (locale) | `pm` / `ب.ظ` |
| `A` | AM/PM (locale) | `PM` / `ب.ظ` |
| `N` | ISO weekday (Mon=1..Sun=7) | `3` |
| `w` | Weekday (Sun=0..Sat=6) | `3` |
| `t` | Days in month | `31` |
| `L` | Leap year (1/0) | `0` |
| `P` | UTC offset `+03:30` | `+03:30` |
| `p` | UTC offset `+03:30` or `Z` for UTC | `Z` |
| `T`/`e` | Timezone label | `Asia/Tehran` |

Backslash escapes the next character: `\Y` produces a literal `Y`.

> **`W` and `o` tokens at calendar boundaries:** `weekOfYear()` and `weekBasedYear()` can throw `WeekAtBoundaryException` when the ISO week's Thursday falls outside the calendar's supported year range. This affects roughly the first or last 3 days of MIN_YEAR / MAX_YEAR for each calendar. If you format dates near these extremes, catch the exception or avoid the `W` / `o` tokens.

> **Parsing support:** `parseExact()` accepts: fixed-width `Y`, `m`, `d`, `H`, `h`, `i`, `s`; variable-width `n`, `j`, `G`, `g`; meridiem `a`/`A`; timezone offsets `P`, `p`, `O`; and the composite `c` token (`Y-m-d\TH:i:sP`). Locale-dependent tokens (`F`, `M`, `l`, `D`) are format-only. Variable-width tokens must be followed by a literal separator, not another token. Using `h` (12-hour) requires a companion `a`/`A` token. Digits in any script (Persian U+06F0, Arabic-Indic U+0660) are normalized automatically. The `a`/`A` tokens accept English (`am`/`pm`), Persian (`ق.ظ`/`ب.ظ`), and Arabic (`ص`/`م`) meridiem indicators.

## Opt-in global helpers

If you're migrating from `morilog/jalali` and miss the `jdate()` shorthand,
add this to your own application's `composer.json`:

```json
{
    "autoload": {
        "files": ["vendor/eramhq/daynum/src/helpers.php"]
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
│   ├── ParseException.php          # thrown by parseExact() on bad input
│   ├── UmmAlQuraOutOfRangeException.php  # thrown by Hijri UAQ when out of bundled range
│   └── WeekAtBoundaryException.php # thrown by weekOfYear()/weekBasedYear() at range edges
├── Formatter/
│   ├── DateTokenFormatter.php      # PHP date()-token engine
│   ├── FormatContext.php
│   └── DigitTransliterator.php     # latn ↔ persian (U+06F0) ↔ arab (U+0660)
├── Locale/
│   ├── LocaleData.php
│   ├── LocaleRegistry.php
│   ├── AbstractTableLocale.php
│   ├── EnglishLocale.php
│   ├── PersianLocale.php
│   └── ArabicLocale.php
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
* Arabic Jalali output — ICU's Arabic transliterations of Persian month names are low quality, so Arabic + Jalali throws on `F`/`M` tokens. Use the Persian locale instead; it renders in the same Perso-Arabic script
* Hebrew, Buddhist, Japanese, Indian, Coptic, Ethiopic — post-v1 milestones, each with its own ICU oracle
* Timezone arithmetic — delegate to native `DateTimeImmutable` via `toDateTimeImmutable()`
* Sub-second precision, leap seconds, Julian/Gregorian cutover
* A framework bridge — a separate `daynum/laravel` package can ship post-v1 if demand exists

## License

MIT. See `LICENSE`.
