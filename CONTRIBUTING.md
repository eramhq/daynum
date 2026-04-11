# Contributing to Daynum

Daynum is a small, tightly scoped library. Contributions are welcome, especially bug reports, documentation fixes, new locales, and new calendar systems backed by an ICU oracle.

## Before you start

- **File an issue first** for anything bigger than a typo fix or a one-line bug. Daynum has strong opinions about scope (see `README.md` and [docs/en/concepts.md](docs/en/concepts.md#scope-what-v1-does-and-doesnt-ship)), so a quick sanity check saves you from writing code that won't land.
- **Read [docs/en/concepts.md](docs/en/concepts.md)** so you know the `Instant`-vs-view split, the "civil, not UTC" rule, and why arithmetic returns `Instant`. A lot of contributor confusion goes away once these are clear.
- **Read [docs/en/algorithms-and-attribution.md](docs/en/algorithms-and-attribution.md)** if you are touching any calendar math. Every calendar is a port of a published algorithm with attribution headers — keep them intact.

## Running the test suite

Daynum ships with four test suites, all runnable without `ext-intl` because the ICU oracle fixtures are committed to the repo.

```bash
composer install

vendor/bin/phpunit                         # everything: ~220 tests, ~240k assertions
vendor/bin/phpunit --testsuite=unit         # pure unit tests
vendor/bin/phpunit --testsuite=edgecase     # boundary & leap-year hand-checks
vendor/bin/phpunit --testsuite=property     # randomized round-trip & ordering invariants
vendor/bin/phpunit --testsuite=conformance  # ~750k-row ICU differential test across all four calendars
```

Equivalent composer scripts:

```bash
composer test               # phpunit
composer test:unit
composer test:edgecase
composer test:property
composer test:conformance
```

### Static analysis

```bash
composer phpstan            # phpstan level 8
```

All PRs must be clean against the bundled PHPStan configuration.

## The conformance fixtures

The differential-test fixtures under `tests/fixtures/` are committed as gzipped JSONL. Developers do **not** need `ext-intl` to run the conformance suite — it's pure PHP reading pre-generated oracle output.

Fixture refresh is a deliberate, reviewed action performed by maintainers (or contributors touching calendar math). Do it only when:

1. You added a new calendar, format token, or locale
2. An upstream ICU update produces different output for known dates
3. CI's `oracle.yml` workflow reports that the PHP and Node oracles disagree

### Procedure

```bash
# 1. Regenerate the bundled UAQ table (only if ICU version changed)
php tools/generate-uaq-table.php   # writes src/Calendar/Hijri/Table.php

# 2. Regenerate from PHP (requires ext-intl)
php tools/generate-fixtures-php.php
php tools/generate-format-tokens.php

# 3. Regenerate from Node (requires a recent Node — 20.x or newer)
node tools/generate-fixtures-node.mjs

# 4. Verify PHP ↔ Node agree byte-for-byte
php tools/verify-oracles-agree.php

# 5. Re-run the full conformance suite
vendor/bin/phpunit --testsuite=conformance

# 6. Review git diff carefully before committing
git diff tests/fixtures/ src/Calendar/Hijri/Table.php
```

**Do not check in fixture changes without running both oracles.** A single-oracle change is almost always an ICU version drift, not a real algorithm change, and we want both sides to agree before the CI record of truth moves.

See `tests/fixtures/README.md` for the full row-format documentation and pinned-version metadata.

## Setting up the ICU oracle

The PHP oracle needs `ext-intl` compiled against a recent ICU. On macOS:

```bash
brew install icu4c
PKG_CONFIG_PATH="$(brew --prefix icu4c)/lib/pkgconfig" \
    pecl install intl
```

On Debian/Ubuntu:

```bash
sudo apt install php-intl
```

Verify:

```bash
php -i | grep 'ICU version'
```

The Node oracle uses Node's bundled ICU (`node -e 'console.log(process.versions.icu)'`). No installation beyond Node itself.

## How to add a new calendar

Post-v1, Daynum will ship Hebrew, Buddhist, Japanese, Indian, Coptic, and Ethiopic. The process:

1. **Write the `Calendar` implementation** in `src/Calendar/<Name>/<Name>Calendar.php`. It must implement `Daynum\Calendar` — `toJdn`, `fromJdn`, `isLeapYear`, `daysInMonth`, `dayOfYear`, `monthsInYear`, `name`, `localeFamily`, `supportedRange`, `supportsYear`.

2. **Write a stateless singleton**: `public static function instance(): self`.

3. **Add the view**: `src/Calendar/<Name>/<Name>View.php` extends `AbstractCalendarView` and only overrides `calendar()`, `calendarInstance()`, and `defaultFormat()`.

4. **Wire it into `Instant`** with `fromX()`, `tryFromX()`, `isValidX()`, and an accessor method (`$instant->x(): XView`).

5. **Add month names** to each `Locale/*Locale.php` table under a new `localeFamily()` key. Locales that don't define names for this calendar should inherit the base-class throw behavior — don't ship bad transliterations.

6. **Add an ICU oracle** in both `tools/generate-fixtures-php.php` and `tools/generate-fixtures-node.mjs`. The oracle must cover a date range at least as wide as the conformance suite's Gregorian input range.

7. **Regenerate the fixtures** as above. Commit the new `.jsonl.gz` files.

8. **Add conformance tests** in `tests/Conformance/` that walk the new fixture. Add unit tests in `tests/Unit/`, edge-case tests in `tests/EdgeCase/`, and property tests in `tests/Property/`.

9. **Add calendar docs**: `docs/en/calendars/<name>.md` following the template of existing calendar pages.

10. **Update `README.md` and `docs/en/concepts.md`** to list the new calendar.

If any of these steps is unclear, file an issue before writing code. Reviewing half-finished calendar ports is hard and demotivating for everyone.

## Adding a new locale

1. Create `src/Locale/<Xxx>Locale.php` extending `AbstractTableLocale`, with tables for every `localeFamily()` you want to support.
2. Register the new tag in `src/Locale/LocaleRegistry.php`.
3. If the locale does not define names for some calendars (e.g., Arabic + Jalali), explicitly document it in the class docblock and let the base class throw.
4. Add a golden-string fixture under `tests/fixtures/format-tokens-<lang>-*.jsonl.gz` generated from `tools/generate-format-tokens.php`.
5. Add conformance tests under `tests/Conformance/` that compare against the new fixtures.

## Commit style

- One logical change per commit
- Commit messages follow the existing repo style: imperative, subject ≤70 chars, optional body explaining the "why"
- Keep attribution headers intact when porting algorithms
- Never commit `.phpunit.cache/` or editor droppings

## PR checklist

Before opening a PR:

- [ ] `vendor/bin/phpunit` passes
- [ ] `composer phpstan` passes
- [ ] New or changed code has tests (unit + edge-case at minimum)
- [ ] If you touched a calendar: the conformance suite runs green
- [ ] If you touched public API: docs under `docs/en/` are updated and `docs/en/api-reference.md` reflects the change
- [ ] Attribution headers in ported files are untouched

## Code style

- PHP 8.1+ strict types (`declare(strict_types=1);`)
- Every value type is `final` with `readonly` properties
- Prefer explicit exceptions over `null` return when the caller must know about the failure — ship a safe variant (`tryFoo` / `isValidFoo`) alongside
- Short comments explain "why", not "what". Inline comments are welcome where the algorithm is subtle (see `JalaliCalendar::jalCal` or the UAQ bit-walk for examples of the target density)

## Questions

Open a discussion or an issue at <https://github.com/eramhq/daynum/issues>.
