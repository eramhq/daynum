# Changelog

All notable changes to Daynum are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed
- Mutation testing now runs every suite, including the ICU conformance and
  property suites that pin down the calendar math. The mutation job samples
  them through two test-only environment variables: `DAYNUM_FIXTURE_STRIDE`
  keeps every Nth fixture row (by JDN, so the sample is deterministic) and
  `DAYNUM_PROPERTY_SCALE` scales the seeded iteration counts. Both default
  to the full run. The job also uploads `infection.log` as an artifact.
- Mutation score: covered-code MSI rose from 85% to 99% and the CI floor
  from 83% to 90%. New unit tests cover the parser's edge cases (offsets at
  ±14/−12 hours, 5-digit and negative years, escapes around `c`, names
  followed by multi-byte letters), exact exception messages, every season
  name, the Arabic plural table, calendar month lengths and range edges,
  `CivilDateTime::fromArray()` type errors and relative-time thresholds.
  Provably equivalent mutants are listed in `infection.json5`, each with
  its reason.
- Internal simplifications with identical behaviour: ISO week numbers count
  from day 1 of the Thursday's year, the Umm al-Qura month walk and the
  relative-time overshoot check lost dead branches, and digit
  transliteration uses constant `strtr()` tables instead of lazily built
  ones.

## [1.0.0-beta.2] — 2026-10-01

### Breaking
- `Instant` is renamed to `CivilDateTime`. The old name suggested a UTC
  moment (as in `java.time.Instant` or JS `Temporal.Instant`); the value is a
  wall-clock date-time with an optional timezone label. The rename is
  mechanical, with no alias: `use Eram\Daynum\Instant` →
  `use Eram\Daynum\CivilDateTime`. The JSON shape is unchanged.
- `CalendarView::instant()` is renamed to `CalendarView::dateTime()`.
- `startOfWeek()` / `endOfWeek()` with no argument now start the week on
  the view locale's first day instead of always Monday. The default `en`
  locale still uses Monday; `withLocale('fa')` views now use Saturday and
  `withLocale('ar')` views Sunday. Custom `LocaleData` implementations must
  add `firstDayOfWeek()`, `weekendDays()`, `relativeTime()`,
  `relativeTimeNow()` and `seasonName()`.

### Added
- Timestamps: `CivilDateTime::fromTimestamp(int $ts, string $tz = 'UTC')` and
  `toTimestamp()`. `toTimestamp()` throws `MissingTimezoneException` (new
  `forOperation()` factory) when no timezone label is set.
- Wall-clock time arithmetic on `CivilDateTime`: `add/subSeconds`,
  `add/subMinutes`, `add/subHours`, `add/subDays`, `add/subWeeks`,
  `startOfDay()`, `endOfDay()`. Not DST-aware by design; use timestamps for
  exact elapsed time.
- `diffInSeconds()`, `diffInMinutes()`, `diffInHours()` (wall-clock,
  truncated toward zero). `diffInDays()` stays a calendar-day difference.
- Locale-aware weeks: `LocaleData::firstDayOfWeek()` and `weekendDays()`
  (en: Monday / Sat–Sun, fa: Saturday / Fri, ar: Sunday / Fri–Sat), and view
  methods `weekDay(): WeekDay`, `isWeekend()`, `isWeekday()`.
- Relative time: view methods `diffForHumans(CivilDateTime $other)` and
  `ago()` ("3 days ago", "in 2 hours", "۳ روز پیش", "قبل ٣ أيام"), using
  the largest whole unit and counting months/years in the view's calendar.
  Equal values read "now" (اکنون, الآن) rather than "0 seconds ago".
  Backed by `LocaleData::relativeTime()` / `relativeTimeNow()`, with Arabic plural forms, and
  conformance-tested against `Intl.RelativeTimeFormat` via a new Node oracle
  (`tools/generate-relative-time-node.mjs`).
- Custom locales: `LocaleRegistry::register()`, `has()`, `tags()` and
  `normalize()`. Tags are case-insensitive, treat `_` as `-` and fall back
  from region to language (`fa-IR` → `fa`). `EnglishLocale`, `PersianLocale`
  and `ArabicLocale` are no longer `final`, so they can be extended.
- Dari (`fa-AF`) locale: Afghan Jalali month names (حمل, ثور, جوزا, …),
  `fa-AF` Gregorian month names and a Thursday–Friday weekend, ICU-tested
  through new `format-tokens-fa-af*.jsonl.gz` fixtures.
- `parseExact()` / `tryParseExact()` take a `?string $locale` argument and
  parse `F`/`M` (month names, full or short) and `l`/`D` (weekday names,
  checked against the date). Matching ignores Latin case, treats Arabic
  ي/ى/ك as Persian ی/ک and ignores ZWNJ and the ezafe hamza.
- `with(year:, month:, day:, hour:, minute:, second:)` on views: replace
  some parts of the date in the view's calendar. Validates instead of
  clamping.
- Quarters on views: `quarter()`, `startOfQuarter()`, `endOfQuarter()`.
- Seasons: `Season` enum, `JalaliView::season()` / `seasonName()` and
  `LocaleData::seasonName()` (Dari uses خزان for autumn).
- `CivilDateTime::compare()` (a `usort` callback), `min()`, `max()`,
  `between($a, $b, bool $inclusive = true)` and `isSameDay()`.

### Removed
- The opt-in global helpers `gdate()`, `jdate()` and `hdate()` (`src/helpers.php`).
  Their `jdate(int, int, int)` signature clashed with both `morilog/jalali`'s
  `jdate($str = null)` and jdf.php's `jdate($format, $timestamp)`, and the
  `function_exists` guard turned that clash into silent runtime breakage. Call
  `CivilDateTime::fromJalali()` etc. directly, or define a helper in your own namespace.

### Fixed
- `toDateTimeImmutable()` (and the timezone format tokens built on it)
  resolved an ambiguous fall-back reading such as 01:30 on a DST-ending
  night depending on the DST state of the day the code ran. It now always
  resolves to the earlier moment, and also works for years beyond 9999
  reached through arithmetic.
- CI had been red since beta.1: the test workflow's Jalali conformance
  check required a gitignored Node fixture, and the oracle workflow
  compared fixtures byte-for-byte, so the runner's ICU version in each
  header always failed it. The Jalali check now works without the Node
  file, the oracle compares data rows only (`tools/check-fixture-drift.php`),
  and it pins ICU 78.2, the version the fixtures were built with.
- `diffInMonths()` counted 12 months per year regardless of the calendar.
  It now walks `Calendar::monthsInYear()` the same way `addMonths()` does,
  so the two stay inverse for days 1–28.

### Changed
- `parseExact()` now rejects formats whose tokens give one field two
  different values (e.g. `F` and `m` naming different months); previously
  the last token silently won.
- `LocaleRegistry::get()`'s unknown-locale message lists all available tags,
  and malformed tags such as `en-` now throw instead of resolving to `en`.
- Code style: PER-CS 2.0 via php-cs-fixer (`composer cs`, `composer cs:fix`),
  checked in CI. The codebase was reformatted once to match.
- CI also reports line coverage and runs Infection mutation testing.
- Added SECURITY.md (private reporting through GitHub) and issue templates.
- CI runs PHPStan through `composer phpstan` (same memory limit as local runs)
  and adds PHP 8.5 to the test matrix. The oracle workflow uses Node 24, the
  version the committed relative-time fixture was generated with.

### Docs
- Rewrote the `morilog/jalali` migration guide as an explicit before/after
  table. It previously claimed existing `jdate()` calls keep working unchanged,
  which was never true.

## [1.0.0-beta.1] — 2026-04-12

### Breaking
- Root PHP namespace renamed from `Daynum\` to `Eram\Daynum\`, aligning with the
  `eram/daynum` Composer vendor and with PHP community conventions
  (Symfony/Doctrine/PHPUnit-style vendor-prefixed roots). Every
  `use Daynum\Foo` in downstream code must become `use Eram\Daynum\Foo`;
  the rename is mechanical with no class aliases or compatibility shim.

### Added
- `Eram\Daynum\Exception\InvalidArgumentException` — library-owned exception
  extending `\InvalidArgumentException` and implementing `DaynumException`.
  All bare `\InvalidArgumentException` throws in library code (`Instant::fromArray`,
  `CalendarView::of`, `CalendarView::withDigits`, `CalendarView::startOfWeek`,
  `LocaleRegistry::get`) now throw this class, sealing the `DaynumException`
  marker-interface contract: `catch (DaynumException)` catches every exception
  the library throws.
- PHPStan level 8 static analysis in CI. `phpstan.neon` ships with the library;
  `composer phpstan` runs the analysis locally.

- `WeekDay` backed enum (ISO Mon=1..Sun=7) for self-documenting
  `startOfWeek(WeekDay::Saturday)` / `endOfWeek(WeekDay::Sunday)` calls.
  `startOfWeek` / `endOfWeek` now accept `WeekDay|int`; existing `int`
  callers are unaffected.
- `InvalidTimezoneException` — thrown by `Instant::now()`, `today()`, and
  `toDateTimeImmutable()` when a stored timezone label is invalid or
  unknown. Replaces the raw PHP `DateInvalidTimeZoneException` /
  `\Exception` that previously leaked.

### Fixed
- `parseExact()` negative UTC offset validation now correctly rejects
  offsets below `-12:00` (Baker Island). Previously the validation was
  symmetric, allowing `-14:00` which has no real-world IANA timezone.
  The valid parsed range is now `-12:00` to `+14:00`.
- `e` format token now wraps its output in null-byte sentinels, matching
  all other timezone tokens (`P`, `O`, `T`, etc.). Previously, formatting
  `e` with a numeric offset like `+03:30` under Persian digits produced
  `+۰۳:۳۰` instead of the correct `+03:30`.
- `parseExact()` now range-validates parsed UTC offsets: hours must be
  0–14 (positive) or 0–12 (negative), minutes 0–59. Previously, offsets
  like `+99:99` passed the regex match and were stored verbatim,
  producing a deferred PHP exception when `toDateTimeImmutable()` was
  eventually called.
- `Instant::now()`, `today()`, and `toDateTimeImmutable()` now catch
  PHP's timezone exception and rethrow as `InvalidTimezoneException`,
  ensuring all exceptions from the library implement `DaynumException`.

- PHP `date()` format token `o` — ISO 8601 week-based year. Pairs with
  `W` to emit `Y-W`-style identifiers that round-trip through ISO week
  arithmetic. Differs from `Y` by ±1 around the Jan 1 / Dec 31 edge:
  `Instant::fromGregorian(2024, 12, 30)->gregorian()->format('o-\WW')`
  correctly returns `2025-W01` whereas `Y-\WW` would have returned the
  misleading `2024-W01`. Same negative-year and padding semantics as
  `Y`. Not included in format-token conformance fixtures — ICU's `Y`
  ≠ PHP `o`, so `o` joins `W g h S z` as a unit-test-only token.
- `CalendarView::weekBasedYear(): int` — underlying primitive that
  powers the `o` token. Throws `WeekAtBoundaryException` on the same
  MIN/MAX-year boundary conditions as `weekOfYear()`.
- `Eram\Daynum\Exception\WeekAtBoundaryException` — dedicated exception
  thrown by `weekOfYear()` / `weekBasedYear()` when the containing ISO
  week's Thursday, or the resulting week-based year, falls outside the
  calendar's supported range. Implements the `DaynumException` marker
  interface, so existing `catch (DaynumException)` clauses pick it up.
  Boundary scope is covered in the `Changed` entry below.
- PHP `date()` format tokens `g`, `h`, `W`, `S`:
  - `g` / `h` — 12-hour clock (unpadded / zero-padded). Midnight and
    noon both render as `12`, matching PHP's `date()` semantics.
  - `W` — ISO 8601 week number, zero-padded. Uses the existing
    `AbstractCalendarView::weekOfYear()`. Non-Gregorian views emit the
    ISO week of the calendar's own year — Jalali `W` is "ISO week of
    the Jalali year", HijriCivil / UAQ likewise. Guarded behind a
    `str_contains($pattern, 'W')` hot-path check, matching the `z`
    pattern from the previous tranche. Follows the ISO Thursday rule,
    so cross-year edges (e.g. 2024-12-30 → W=01, 2023-01-01 → W=52)
    match PHP's `date('W')` output.
  - `S` — English ordinal suffix (`st`/`nd`/`rd`/`th`). Routes through
    a new `LocaleData::ordinalSuffix()` method; English returns the
    correct suffix with the 11/12/13 override, Persian and Arabic
    return the empty string so `jS F Y` renders cleanly in every
    locale instead of leaving broken `th` residue inside Perso-Arabic
    output.
  - None of the four are added to format-token conformance fixtures —
    ICU semantics don't map cleanly for any of them (see the
    docblock in `tools/generate-format-tokens.php` for the existing
    exclusion policy).
- PHP `date('z')` day-of-year format token — 0-indexed day of the
  calendar year. Works across Gregorian, Jalali, HijriCivil, and
  HijriUmmAlQura via the `Calendar::dayOfYear` interface method
  added in the previous tranche. Not included in format-token
  conformance fixtures (ICU's `D` is 1-indexed; `z` joins
  `N w L t T e` as a unit-test-only token).
- Arabic locale (`ar`, `ar-sa`, `ar_sa`) with Gregorian and Hijri
  month/weekday/meridiem strings sourced byte-for-byte from ICU 78.2
  `ar-SA`. Short forms alias long forms because ICU does not abbreviate
  Arabic month or weekday names. Meridiem is `ص`/`م` (unicase).
- `format-tokens-ar.jsonl.gz` and `format-tokens-ar-hijri.jsonl.gz`
  conformance fixtures.
- `ArabicLocaleTest` with 22 unit tests covering the new locale and
  the Arabic + Jalali throw-path (see below).
- `arabic` keyword in `composer.json` (post-Arabic-tranche cleanup).
- `GregorianCalendar::dayOfYear(int $year, int $month, int $day): int`
  — 1-indexed day of year, proleptic Gregorian. Used internally by
  `JalaliCalendar::fromJdn` (see Performance below) and a useful
  addition to the public surface for ISO-week and YTD arithmetic.
- `JalaliCalendar::dayOfYear`, `HijriCivilCalendar::dayOfYear`,
  `HijriUmmAlQuraCalendar::dayOfYear` — 1-indexed day of year for
  each remaining shipped calendar, extracted from the closed-form
  math already embedded in each calendar's `toJdn`. Satisfies the
  new `Calendar::dayOfYear` interface contract (see Breaking below).
- `tools/bench.php` — minimal `hrtime`-based micro-benchmark harness
  with warmup, GC control, and JIT-on/off comparison support. No
  dependencies. The numbers below were captured with this harness.

### Performance
M3 milestone — three targeted optimizations on the Jalali hot path,
zero behavior change. Numbers are µs/op, JIT-off, 50k iterations.

- **Memoize `JalaliCalendar::jalCal`** — instance-level cache bounded
  to `MIN_YEAR..MAX_YEAR` so adversarial out-of-range callers cannot
  grow it without bound. Eliminates 2 of the 3 `jalCal` invocations
  per format() round-trip.
- **Cache `DigitTransliterator::toScript` strtr map** — once-per-script
  population (max 2 entries), eliminates a 10-entry array allocation
  per non-Latin format call.
- **Eliminate redundant `toJdn` in `JalaliCalendar::fromJdn`** —
  rewrites the day-of-Jalali-year offset to use the new
  `GregorianCalendar::dayOfYear` helper, dropping a full Gregorian
  `toJdn` (with validation) per `fromJdn`.

| benchmark              | before | after | win  |
|------------------------|--------|-------|------|
| jalali.toJdn.warm      |  1.07  | 0.69  | -36% |
| jalali.fromJdn.warm    |  2.09  | 1.06  | -49% |
| jalali.format.numeric  |  3.00  | 1.97  | -35% |
| jalali.format.textual  |  3.15  | 2.11  | -33% |
| jalali.format.persianDigits | 3.49 | 2.34 | -33% |

Wins are similar magnitude under JIT-on (`opcache.jit=tracing`).
Gregorian and Hijri benchmarks are unchanged within ±2% noise.

### Changed
- `AbstractCalendarView::dayOfYear()` now delegates to the backing
  calendar's `dayOfYear` instead of round-tripping through `toJdn`
  (which included the full validation prelude). Behavior is
  unchanged for every shipped view — this is a structural cleanup
  that closes the M3 review's open finding without special-casing
  Gregorian in the abstract base.
- `AbstractCalendarView::weekOfYear()` now throws
  `WeekAtBoundaryException` at MIN_YEAR / MAX_YEAR edges where the
  containing ISO week's Thursday lies outside the calendar's supported
  range. An earlier commit in this unreleased tranche returned `1` as
  a sentinel; that was silently wrong at the MIN edge — it collided
  with the real week 1 of MIN_YEAR, so `(year, week)` sorts across a
  boundary silently corrupted two distinct weeks into one label.
  Throwing is the only outcome that never misleads: Java `java.time`
  and Joda-Time take the same approach at bounded-chronology edges.
  The throw fires on ~0–3 days per calendar at each boundary;
  mid-year code is unaffected.
- `AbstractCalendarView::format()` hot-path guards (`z`, `W`, `o`) are
  now escape-aware via a new private `patternContainsUnescaped()`
  helper. `\z`, `\W`, `\o` no longer trigger the underlying
  computation and — critically — no longer trip the new boundary
  throw for users who escaped the token on purpose. A zero-allocation
  `str_contains` pre-check short-circuits each guard when the token
  is absent entirely (the common case — `Y-m-d`, `l j F Y`, `H:i:s`),
  so the added guard is free for the typical format call.
- `LocaleRegistry::get()` error message now names all three shipped
  locales: `Daynum ships 'en', 'fa', and 'ar'.`
- Fixture generators (`generate-fixtures-php.php`,
  `generate-fixtures-node.mjs`, `generate-format-tokens.php`) no longer
  emit a wall-clock `generated` timestamp in the fixture header. The
  output is now byte-deterministic for a given ICU version, which is
  what the oracle CI's new fail-on-drift check needs.
- `.github/workflows/oracle.yml`:
  - Regenerates `src/Calendar/Hijri/Table.php` from the runner's ICU
    before regenerating fixtures, so the table, fixtures, and ICU are
    self-consistent during CI runs.
  - Fixture-drift check now **fails** the build instead of warning,
    and additionally watches `src/Calendar/Hijri/Table.php`
    (ignoring only the `GENERATED_AT` constant).

### Fixed
- `format('\z')` no longer forces a `dayOfYear()` computation on the
  backing calendar. Previous versions documented this as a "benign
  false positive" of the `str_contains($pattern, 'z')` guard; the new
  escape-aware guard short-circuits cleanly.

### Not supported under Arabic locale
- Jalali month-name output: ICU's Arabic transliteration of Persian
  month names is low quality. Calling `F`/`M` format tokens on an
  Arabic Jalali view throws `InvalidArgumentException: Unknown
  calendar family: jalali`. Numeric patterns like `Y-m-d` and the
  weekday token `l` still work. Users who want Jalali output in the
  Perso-Arabic script should use `withLocale('fa')`.

### Breaking (external `Calendar` implementers only)
- The `Calendar` interface gained
  `dayOfYear(int $year, int $month, int $day): int`. Anyone
  implementing the interface in their own code — not a documented
  use case, but possible — must add the method. All calendars
  shipped by Daynum have it. Same kind of source-level break as
  M2's `localeFamily()`; the `1.0.0-beta` pre-release range signals
  the public API may still receive minor adjustments before stable.

### Breaking (external `CalendarView` implementers only)
- The `CalendarView` interface gained `weekBasedYear(): int`. Anyone
  implementing the view interface directly — not a documented use
  case; `AbstractCalendarView` covers every shipped calendar — must
  add the method. `weekOfYear()` and `weekBasedYear()` both now
  document `@throws WeekAtBoundaryException`; external implementers
  should either mirror the throw at MIN/MAX edges or document their
  own boundary semantics.

### Breaking (external `LocaleData` implementers only)
- The `LocaleData` interface gained
  `ordinalSuffix(int $day): string`. All three shipped locales
  implement it (English returns the PHP-compatible `st`/`nd`/`rd`/`th`
  table with the 11/12/13 override; Persian and Arabic return the
  empty string). Custom implementers must add the method — same kind
  of source-level break as this tranche's `Calendar::dayOfYear`
  addition.

## [0.2.0] — M2 Hijri support

### Added
- `HijriCivilCalendar` — tabular Reingold–Dershowitz "Arithmetic
  Islamic" calendar, year-16 leap variant. Matches ICU
  `islamic-civil` byte-for-byte on all 219,510 days in the
  1700–2300 Gregorian fixture window. Range: AH 1 through AH 9666.
- `HijriUmmAlQuraCalendar` — Saudi KACST calendar, backed by a
  bundled table (`src/Calendar/Hijri/Table.php`) generated verbatim
  from ICU's `islamic-umalqura` data. Native range AH 1300..1600.
  Throws `UmmAlQuraOutOfRangeException` outside that window rather
  than silently falling back to the civil calendar.
- `Instant::fromHijri()` and `Instant::fromHijriCivil()` constructors
  and `hijri()` / `hijriCivil()` views.
- `hdate()` opt-in global helper alongside `gdate()` / `jdate()`.
- `Calendar::localeFamily(): string` interface method so the two
  Hijri calendars can share one `hijri` locale table while still
  reporting distinct calendar names.
- `UmmAlQuraOutOfRangeException::forYear()` and `::forJdn()`
  named factories.
- `tools/generate-uaq-table.php` — regenerates `Table.php` from
  ICU and cross-verifies against Rob van Gent's academic table.
- Conformance fixtures: `hijri-civil.jsonl.gz` (~220k rows),
  `hijri-umalqura.jsonl.gz` (~107k rows),
  `format-tokens-{en,fa}-hijri.jsonl.gz`.
- 75+ new Hijri unit, edge-case, property, and conformance tests.
- English and Persian Hijri month-name tables (from ICU's
  `en-US` / `fa-IR` `islamic-civil` output).

### Changed
- `Ymd::validateYearMonth()` and `Ymd::validateDay()` replace the
  previous inline validation preludes in every calendar's `toJdn`
  (M1-deferred cleanup).
- `AbstractCalendarView::addMonths()` routes through
  `$calendar->monthsInYear($year)` instead of hardcoded 12.

### Breaking (external `Calendar` implementers only)
- The `Calendar` interface gained `localeFamily(): string`. Anyone
  implementing the interface in their own code — not a documented
  use case, but possible — must add the method. All calendars
  shipped by Daynum have it. The `1.0.0-beta` pre-release range
  signals the public API may still receive minor adjustments before
  stable.

## [0.1.0] — M1 initial release

### Added
- `Instant` — immutable, calendar-agnostic core value type keyed on
  Julian Day Number.
- `Calendar` and `CalendarView` interfaces.
- `GregorianCalendar` — proleptic Fliegel–Van Flandern algorithm,
  cross-checked against Reingold–Dershowitz *Calendrical
  Calculations*.
- `JalaliCalendar` — 33-year Birashk cycle ported from `jalaali-js`,
  supported range 1–3177 AP.
- `DateTokenFormatter` — PHP `date()` token syntax (`Y m d F l H i s`
  …), NOT ICU patterns.
- `DigitTransliterator` — `latn` / `persian` (U+06F0) / `arab`
  (U+0660) digit scripts.
- English and Persian locales with month / weekday / meridiem tables.
- `gdate()` / `jdate()` opt-in global helpers.
- Differential ICU conformance suite over ~220,000 Gregorian and
  ~220,000 Jalali rows, with PHP and Node oracle cross-checks.
- CI matrix across PHP 8.1 / 8.2 / 8.3 / 8.4 against committed
  fixtures; separate `oracle.yml` workflow for fixture refresh.
