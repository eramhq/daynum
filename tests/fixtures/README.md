# Daynum conformance fixtures

These files are the committed oracle data used by `tests/Conformance/` to
verify that Daynum's calendar math agrees with ICU on every day in the range
**1700-01-01 through 2300-12-31 Gregorian** (~220,000 rows per calendar).

Developers do NOT need `ext-intl` to run the conformance suite — the fixtures
are pre-committed so `vendor/bin/phpunit --testsuite=conformance` is pure PHP.
Fixture refresh is a deliberate, reviewed action performed by maintainers.

## Files

| File                              | Rows      | Produced by                          |
|-----------------------------------|-----------|--------------------------------------|
| `gregorian.jsonl.gz`              | ~220,000  | `tools/generate-fixtures-php.php`    |
| `jalali.jsonl.gz`                 | ~220,000  | `tools/generate-fixtures-php.php`    |
| `gregorian.node.jsonl.gz`         | ~220,000  | `tools/generate-fixtures-node.mjs`   |
| `jalali.node.jsonl.gz`            | ~220,000  | `tools/generate-fixtures-node.mjs`   |
| `format-tokens-en.jsonl.gz`       | ~1,000    | `tools/generate-format-tokens.php`   |
| `format-tokens-fa.jsonl.gz`       | ~1,000    | `tools/generate-format-tokens.php`   |
| `format-tokens-en-jalali.jsonl.gz`| ~1,000    | `tools/generate-format-tokens.php`   |
| `format-tokens-fa-jalali.jsonl.gz`| ~1,000    | `tools/generate-format-tokens.php`   |

Node-generated files (`*.node.jsonl.gz`) are NOT consumed by the conformance
suite directly — they exist so `tools/verify-oracles-agree.php` can diff them
against the PHP-generated ones. If Node's ICU and PHP's ICU disagree on any
row, we want to know before committing.

## Row formats

### `gregorian.jsonl.gz`

```json
{"meta":{"icuVersion":"74.2","calendar":"gregorian","range":"1700-01-01..2300-12-31", ...}}
{"jdn":2342032,"g":[1700,1,1],"dow":5}
{"jdn":2342033,"g":[1700,1,2],"dow":6}
...
```

### `jalali.jsonl.gz`

```json
{"meta":{"icuVersion":"74.2","calendar":"persian","range":"1700-01-01..2300-12-31", ...}}
{"jdn":2342032,"g":[1700,1,1],"j":[1078,10,11],"dow":5}
...
```

* `jdn` — integer Julian Day Number (same across both files)
* `g` — `[year, month, day]` Gregorian, 1-indexed month
* `j` — `[year, month, day]` Jalali, 1-indexed month
* `dow` — 0..6, Sunday = 0 (PHP `date('w')` convention)

### Format-token files

```json
{"meta":{...}}
{"jdn":2422060,"expected":{"Y":"1928","m":"01","d":"15","F":"January", ...}}
```

Each `expected` map lists the ICU-rendered output for every token Daynum
claims to support. Daynum's `DateTokenFormatter` must match byte-for-byte.

## Refreshing the fixtures

**Do this only when:**

1. You added a new calendar, format token, or locale
2. An upstream ICU update produces different output for known dates
3. CI's `oracle.yml` workflow reports that the PHP and Node oracles disagree

**Procedure:**

```bash
# 1. Regenerate from PHP (requires ext-intl)
php tools/generate-fixtures-php.php
php tools/generate-format-tokens.php

# 2. Regenerate from Node (requires a recent Node)
node tools/generate-fixtures-node.mjs

# 3. Verify PHP ↔ Node agree byte-for-byte
php tools/verify-oracles-agree.php

# 4. Re-run the full conformance suite
vendor/bin/phpunit --testsuite=conformance

# 5. Review git diff carefully before committing
git diff tests/fixtures/
```

## Pinned versions

Fixture content depends on:

* `intl.icu.version` reported by PHP (`php -i | grep 'ICU version'`)
* `process.versions.icu` reported by Node (`node -e 'console.log(process.versions.icu)'`)
* PHP runtime version
* Node runtime version

These are recorded in the `meta` header line of every fixture file so
reviewers can see at a glance what ICU a given fixture was built against.
