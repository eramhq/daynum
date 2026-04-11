# Daynum Documentation

Drive-by visitors: start with the repo [README](../../README.md) — it has the elevator pitch, install line, and 30-line quick start.

This folder is the deeper documentation: concepts, calendar-specific pages, cookbook recipes, and a full API reference.

## Learn

- [getting-started.md](getting-started.md) — install, first example, 5-minute tour
- [concepts.md](concepts.md) — `Instant` is civil (not UTC), views, JDN, immutability, scope
- [cookbook.md](cookbook.md) — task-indexed recipes (convert, parse, persist, integrate)
- [faq.md](faq.md) — surprising-but-intentional design decisions

## Calendars

- [calendars/gregorian.md](calendars/gregorian.md) — proleptic, year 0, negative years
- [calendars/jalali.md](calendars/jalali.md) — Birashk 33-year cycle, ICU divergence windows
- [calendars/hijri-umm-al-qura.md](calendars/hijri-umm-al-qura.md) — KACST table, AH 1300–1600, `UmmAlQuraOutOfRangeException`
- [calendars/hijri-civil.md](calendars/hijri-civil.md) — `islamic-civil` arithmetic fallback, AH 1–9666

## Reference

- [api-reference.md](api-reference.md) — every type and method, grouped by class
- [formatting.md](formatting.md) — PHP `date()`-token table, escaping, `c`/`r` caveats
- [parsing.md](parsing.md) — `parseExact` grammar, variable-width rules, digit normalization
- [arithmetic.md](arithmetic.md) — month clamping, diffs, boundary behavior
- [localization.md](localization.md) — locales, digit scripts, Arabic + Jalali limitation
- [timezones.md](timezones.md) — civil-vs-UTC semantics, escape hatch to `DateTimeImmutable`
- [exceptions.md](exceptions.md) — 7 concrete types, throw sites, recovery patterns
- [serialization.md](serialization.md) — JSON round-trip contract, DB schema patterns

## Migration & attribution

- [migration-from-morilog-jalali.md](migration-from-morilog-jalali.md) — drop-in migration path
- [algorithms-and-attribution.md](algorithms-and-attribution.md) — ported algorithms, project layout, references

## Contributing

See [CONTRIBUTING.md](../../CONTRIBUTING.md) at the repo root — testing, fixture regeneration, ICU oracle setup, how to add a new calendar.
