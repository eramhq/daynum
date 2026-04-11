# Algorithms and Attribution

Every calendar Daynum ships is a port or implementation of a published algorithm. This page lists the sources, the licenses, and where each algorithm lives in the codebase.

## Ported algorithms

| Component | Ported from | License |
|---|---|---|
| `GregorianCalendar` | Fliegel–Van Flandern algorithm (1968/1990), cross-checked against Reingold–Dershowitz *Calendrical Calculations* (4th ed., 2018) | public reference |
| `JalaliCalendar` | `jalaali-js` — <https://github.com/jalaali/jalaali-js> (also the basis of `morilog/jalali` and `date-fns-jalali`) | MIT |
| `HijriCivilCalendar` | Reingold–Dershowitz *Calendrical Calculations* "Arithmetic Islamic" chapter, cross-checked against ICU `IslamicCalendar.java` (CIVIL type) | public reference + Unicode ICU license |
| `Hijri/Table.php` | Generated verbatim from ICU's bundled `islamic-umalqura` data (originally KACST). Cross-verified against Rob van Gent's academic table at <https://webspace.science.uu.nl/~gent0113/islam/ummalqura.htm>. | Unicode ICU license |

Attribution headers in the ported files name the exact upstream source. `src/Calendar/Jalali/JalaliCalendar.php` in particular contains the full rationale for choosing Birashk, including the divergence-window analysis against ICU's Borkowski implementation.

## Project layout

```text
src/
├── Instant.php                     # immutable core type
├── Calendar.php                    # Calendar interface
├── CalendarView.php                # CalendarView interface
├── WeekDay.php                     # ISO weekday enum
├── helpers.php                     # opt-in jdate/gdate/hdate globals
│
├── Exception/
│   ├── DaynumException.php         # marker interface
│   ├── InvalidArgumentException.php
│   ├── InvalidDateException.php
│   ├── InvalidTimezoneException.php
│   ├── MissingTimezoneException.php
│   ├── ParseException.php
│   ├── UmmAlQuraOutOfRangeException.php
│   └── WeekAtBoundaryException.php
│
├── Formatter/
│   ├── DateTokenFormatter.php      # PHP date()-token engine
│   ├── FormatContext.php
│   └── DigitTransliterator.php     # latn ↔ persian (U+06F0) ↔ arab (U+0660)
│
├── Locale/
│   ├── LocaleData.php              # interface
│   ├── LocaleRegistry.php          # tag → implementation
│   ├── AbstractTableLocale.php     # shared table-driven base
│   ├── EnglishLocale.php
│   ├── PersianLocale.php
│   └── ArabicLocale.php
│
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

See [../../CONTRIBUTING.md](../../CONTRIBUTING.md) for the fixture regeneration procedure and how to add a new calendar.

## Correctness strategy

Daynum is differentially tested against ICU on **~220,000 dates per calendar per CI build**:

- Every day in the range `1700-01-01` through `2300-12-31` Gregorian is converted to each calendar by both Daynum and ICU's implementation.
- The results must match byte-for-byte, with the exception of documented Birashk-vs-ICU divergence windows for Jalali (see [calendars/jalali.md](calendars/jalali.md#jalali-and-icu-a-note-on-correctness)).
- Formatting is tested against ICU-rendered golden strings for every supported token in every supported locale.
- The PHP and Node ICU oracles are cross-checked with `tools/verify-oracles-agree.php` so a single-oracle bug can't poison the fixtures.

The fixtures are committed as gzipped JSONL under `tests/fixtures/`, so the conformance suite runs without `ext-intl` — you don't need ICU installed to verify Daynum.

## References

### General

- Nachum Dershowitz & Edward M. Reingold, *Calendrical Calculations*, 4th ed., Cambridge University Press, 2018.
- Edward G. Richards, *Mapping Time: The Calendar and Its History*, Oxford University Press, 1998.

### Gregorian

- Fliegel & Van Flandern, "A Machine Algorithm for Processing Calendar Dates", *Communications of the ACM*, **11** (1968), 657.

### Jalali (Birashk)

- Ahmad Birashk, *A New Survey of the Persian Calendar*, 1993.
- `jalaali/jalaali-js` — <https://github.com/jalaali/jalaali-js> (MIT)
- ICU `PersianCalendar` (Borkowski variant, **not** what Daynum ships): <http://source.icu-project.org/repos/icu/>

### Hijri

- Reingold & Dershowitz, *Calendrical Calculations* (4th ed.), "Arithmetic Islamic Calendar" chapter.
- ICU `IslamicCalendar.java`, Unicode ICU license.
- Rob van Gent's academic Umm al-Qura table: <https://webspace.science.uu.nl/~gent0113/islam/ummalqura.htm>

## Licensing summary

Daynum is **MIT-licensed**. The upstream algorithms it ports are MIT (`jalaali-js`), Unicode ICU license (the `islamic-umalqura` table, cross-checked ICU implementations), or public-reference algorithms documented in academic literature (Fliegel–Van Flandern, Reingold–Dershowitz).

No upstream license requires dynamic linking or derivative-work labeling beyond attribution, which Daynum preserves in both file-level docblocks and this page.

## See also

- [calendars/gregorian.md](calendars/gregorian.md)
- [calendars/jalali.md](calendars/jalali.md)
- [calendars/hijri-umm-al-qura.md](calendars/hijri-umm-al-qura.md)
- [calendars/hijri-civil.md](calendars/hijri-civil.md)
- [../../CONTRIBUTING.md](../../CONTRIBUTING.md)
