# Changelog

All notable changes to Daynum are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

Version numbers below establish the scheme; git tags have not yet been
applied. Tagging is a separate release decision.

## [Unreleased]

### Added
- Arabic locale (`ar`, `ar-sa`, `ar_sa`) with Gregorian and Hijri
  month/weekday/meridiem strings sourced byte-for-byte from ICU 78.2
  `ar-SA`. Short forms alias long forms because ICU does not abbreviate
  Arabic month or weekday names. Meridiem is `ص`/`م` (unicase).
- `format-tokens-ar.jsonl.gz` and `format-tokens-ar-hijri.jsonl.gz`
  conformance fixtures.
- `ArabicLocaleTest` with 22 unit tests covering the new locale and
  the Arabic + Jalali throw-path (see below).

### Changed
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

### Not supported under Arabic locale
- Jalali month-name output: ICU's Arabic transliteration of Persian
  month names is low quality. Calling `F`/`M` format tokens on an
  Arabic Jalali view throws `InvalidArgumentException: Unknown
  calendar family: jalali`. Numeric patterns like `Y-m-d` and the
  weekday token `l` still work. Users who want Jalali output in the
  Perso-Arabic script should use `withLocale('fa')`.

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
  shipped by Daynum have it. The `0.x.y` version range signals the
  v1 API is still settling.

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
