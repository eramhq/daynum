---
title: "Algorithms and attribution"
description: "Review algorithm provenance, test scope and practical limits."
---
# Algorithms and attribution

## Ported algorithms

| Component | Implementation and source |
|---|---|
| [GregorianCalendar](../../src/Calendar/Gregorian/GregorianCalendar.php) | Integer Gregorian/JDN formula with floor division, described in source as Fliegel–Van Flandern; no historical cutover |
| [JalaliCalendar](../../src/Calendar/Jalali/JalaliCalendar.php) | Break-point `jalCal` calculation following [jalaali-js v1.2.8](https://github.com/jalaali/jalaali-js/blob/v1.2.8/index.js), whose upstream credits Borkowski |
| [HijriCivilCalendar](../../src/Calendar/Hijri/HijriCivilCalendar.php) | 30-year arithmetic Islamic calendar, year-16 leap variant, epoch JDN 1948440; source references Dershowitz and Reingold, *Calendrical Calculations*, 4th ed. (2018) |
| [Hijri table](../../src/Calendar/Hijri/Table.php) | Generated month lengths and year starts from ICU 78.2 `islamic-umalqura`, AH 1300–1600 |
| [Locale tables](../../src/Locale) | ICU-derived names and relative-time data; season names are separately authored and not ICU-conformance-tested |

The “Birashk versus Borkowski” explanation in earlier Daynum documentation was inaccurate: the referenced `jalaali-js` [credits Borkowski](https://github.com/jalaali/jalaali-js#about). ICU's implementation and data also vary by version; an algorithm name alone does not explain every fixture mismatch. Current source comments retain the old wording; see [Jalali limitations](calendars/jalali.md#jalali-and-icu-a-note-on-correctness) for the actual test policy. No runtime algorithm was changed for these docs.

## Correctness strategy

Unit tests cover individual APIs; edge tests cover leap years and range boundaries; seeded property tests check round trips and arithmetic; conformance tests read committed ICU oracle fixtures without runtime `ext-intl`. Gregorian, Jalali and civil Hijri calendar fixtures span 1700–2300 Gregorian. Umm al-Qura fixtures are restricted to the bundled table's range, not that entire window.

Jalali comparisons have an explicit, reconciled divergence policy; they are not universal ICU equality checks. Formatting fixtures cover selected comparable tokens; tokens with different ICU semantics use unit/PHP comparison tests instead. Relative-time fixtures come from Node `Intl.RelativeTimeFormat`. Normal CI runs full budgets; mutation CI samples fixtures and property iterations. See [fixtures](../../tests/fixtures/README.md), [conformance tests](../../tests/Conformance) and [CI](../../.github/workflows/test.yml).

These tests provide evidence for the documented models and sampled domains, not a speed ranking, astronomical guarantee or proof that another library is less correct. Avoid inferring compatibility beyond the tested versions and ranges.

## Limits

- Supported construction years are listed in the [overview](overview.md#choose-a-calendar). Low-level JDN arithmetic may leave them.
- Civil arithmetic and comparisons ignore timezone labels and DST. Native conversions have [precision and ambiguity limits](timezones.md).
- No leap-second or subsecond storage, relative phrase parser, holiday service, Julian calendar, or observational Hijri calendar is provided.
- Locale tables and timezone rules are versioned data. Native timezone output depends on PHP's timezone database; bundled locale output does not require ICU at runtime.

## Licensing summary

Daynum's project license is [MIT](../../LICENSE). `jalaali-js` is [MIT licensed](https://github.com/jalaali/jalaali-js/blob/v1.2.8/LICENSE); ICU data and source carry [Unicode/ICU notices](https://github.com/unicode-org/icu/blob/main/LICENSE). Preserve applicable upstream notices when distributing derived code/data; the project license alone does not replace upstream attribution. Academic references describe algorithms, not a blanket license to copy their text or code.

The [Umm al-Qura generator](../../tools/generate-uaq-table.php) reads ICU and includes an optional external-check path; this page does not claim an independently certified table. Fixture/table regeneration belongs to maintainers and is separate from ordinary application use. See [contributing](../../CONTRIBUTING.md) and [documentation maintenance](../README.md).
