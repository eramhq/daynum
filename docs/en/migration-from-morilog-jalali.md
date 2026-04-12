# Migrating from `morilog/jalali`

Daynum is the direct replacement target for `morilog/jalali`. Same algorithm (Birashk), same API style, a superset of functionality (Gregorian + Hijri too), zero runtime dependencies, immutable, ICU-tested.

This page walks through the common migration paths.

## TL;DR

1. `composer remove morilog/jalali`
2. `composer require eram/daynum:^1.0@beta`
3. Add the opt-in helpers so existing `jdate()` calls keep working:

   ```json
   {
       "autoload": {
           "files": ["vendor/eram/daynum/src/helpers.php"]
       }
   }
   ```

4. `composer dump-autoload`
5. Run your tests. Most should pass unchanged.
6. Fix the small handful of API differences listed below.

## Why the algorithm is the same

Daynum's Jalali implementation is a faithful port of `jalaali-js` — the same algorithm `morilog/jalali` uses. Birashk's 33-year cycle, the same break points, the same leap-year rule. For any input `morilog/jalali` accepted, Daynum produces the same JDN and the same calendar output.

See [calendars/jalali.md](calendars/jalali.md).

## The opt-in helpers

Daynum ships `jdate()`, `gdate()`, `hdate()` helpers in `src/helpers.php`. They are **not** autoloaded by default — you opt in by adding the file to your application's `composer.json`:

```json
{
    "autoload": {
        "files": ["vendor/eram/daynum/src/helpers.php"]
    }
}
```

```php
$d = jdate(1405, 1, 19);         // Instant::fromJalali
$d = gdate(2026, 4, 8);          // Instant::fromGregorian
$d = hdate(1447, 10, 21);        // Instant::fromHijri (Umm al-Qura)
```

Each helper is wrapped in `function_exists` so Daynum will never silently clobber a name your application (or another package) already defines. If your codebase already has its own `jdate()` (very common), Daynum's helper quietly stands down.

## Key differences vs. `morilog/jalali`

### 1. Results are `Instant`, not calendar-specific objects

`morilog/jalali`'s `Jalalian` class mixed calendar and date into one type. Daynum splits them: `Instant` is the value, the view (`jalali()`, `gregorian()`, `hijri()`) is the lens.

```php
// morilog/jalali
$d = Jalalian::fromFormat('Y/m/d', '1405/01/19');
echo $d->format('l j F Y');

// Daynum
$d = JalaliView::parseExact('1405/01/19', 'Y/m/d');   // Instant
echo $d->jalali()->format('l j F Y');                  // enter view to format
```

One extra method call, but now the same `Instant` is also `$d->gregorian()` and `$d->hijri()` without any conversion code.

### 2. Arithmetic returns `Instant`, not a view

```php
// morilog/jalali
$next = $d->addMonths(1);
echo $next->format('Y/m/d');

// Daynum
$next = $d->jalali()->addMonths(1);       // Instant
echo $next->jalali()->format('Y/m/d');    // re-enter view to format
```

Why: arithmetic is calendar-specific, but the result is calendar-neutral. See [concepts.md](concepts.md#instant-vs-view).

### 3. Immutable by default

Every Daynum value type is `final` with `readonly` properties. Methods that look like mutators (`addDays`, `withLocale`, `withTzLabel`) always return a new instance. `morilog/jalali`'s `Jalalian` was effectively immutable in most usage, but Daynum makes it structural.

### 4. Format tokens are the same

Daynum uses PHP `date()` syntax. Every token you used in `morilog/jalali` works unchanged. See [formatting.md](formatting.md).

### 5. Persian digits are opt-in

`morilog/jalali` often configured Persian digits via a global flag. Daynum makes it per-view:

```php
$d->jalali()->withLocale('fa')->withDigits('persian')->format('Y/m/d');
// "۱۴۰۵/۰۱/۱۹"
```

This is more verbose but explicit — no global state, no surprises across request boundaries. If you're migrating a project that sets Persian digits everywhere, wrap the view construction in a small helper:

```php
function jview(Instant $d): JalaliView
{
    return $d->jalali()->withLocale('fa')->withDigits('persian');
}

echo jview($d)->format('Y/m/d');
```

### 6. Parsing is stricter

`morilog/jalali` sometimes accepted partial or sloppy input. Daynum's `parseExact` is strict — format tokens must match input character for character. Locale-dependent tokens (`F`, `M`, `l`, `D`) cannot be parsed at all.

If you parse month names, switch to numeric tokens (`m`, `n`). For free-form user input, use `tryParseExact` and fall back to `DateTimeImmutable`:

```php
$d = JalaliView::tryParseExact($raw, 'Y/m/d')
   ?? JalaliView::tryParseExact($raw, 'Y-m-d')
   ?? Instant::fromDateTime(new DateTimeImmutable($raw));
```

See [parsing.md](parsing.md).

### 7. Timezone handling is civil, not UTC

`morilog/jalali`'s timezone behavior varied by method. Daynum's `Instant` is **civil datetime** — the `tzLabel` is a passthrough string, comparison is wall-clock, and you escape to `DateTimeImmutable` when you need real timezone math.

```php
$d = Instant::fromJalali(1405, 1, 19, 14, 30, 0, 'Asia/Tehran');
$native = $d->toDateTimeImmutable();   // escape hatch for DST/UTC/etc
```

See [timezones.md](timezones.md) and [concepts.md](concepts.md#instant-is-civil-not-utc).

### 8. No relative parsing

`morilog/jalali` inherited Carbon-style relative expressions (`"next Monday"`). Daynum does not. If you relied on them, use PHP's `DateTimeImmutable` constructor first:

```php
$d = Instant::fromDateTime(new DateTimeImmutable('next Saturday'));
```

See [faq.md](faq.md#why-no-relative-date-parsing-next-monday-2-weeks).

## Migrating call sites gradually

If your codebase has thousands of `jdate()` calls, you don't need to rewrite them. The opt-in helpers keep everything working on day one; you migrate individual call sites to the explicit API over time as you touch them.

A typical migration path:

1. Install Daynum, enable the `jdate()` / `gdate()` / `hdate()` helpers.
2. Run the test suite. Fix any failures one at a time — most will be about the arithmetic return type (Instant vs. view) or timezone expectations.
3. Over the next few sprints, rewrite touched call sites to use `Instant::fromJalali` directly.
4. Eventually delete the `files` entry from `composer.json` — at that point your code is fully on the explicit API.

## Common gotchas

### `->format()` suddenly throws

```php
$d = jdate(1405, 1, 19)->format('Y/m/d');
// → Error: Call to undefined method Eram\Daynum\Instant::format()
```

`Instant` doesn't have `format()`. Enter a view:

```php
$d = jdate(1405, 1, 19)->jalali()->format('Y/m/d');
```

### Chained arithmetic + format

```php
// Wrong
jdate(1405, 1, 19)->jalali()->addMonths(1)->format('Y/m/d');
// → Error: Call to undefined method Eram\Daynum\Instant::format()

// Right
jdate(1405, 1, 19)->jalali()->addMonths(1)->jalali()->format('Y/m/d');
```

The intermediate `addMonths(1)` is an `Instant`, so you re-enter the view.

### Arabic month names on Jalali throw

If you format Jalali with `withLocale('ar')` and an `F`/`M` token, Daynum throws. Use `withLocale('fa')` instead — Persian month names render in the same Perso-Arabic script. See [localization.md](localization.md#the-arabic--jalali-limitation).

## Feature parity checklist

| Feature | `morilog/jalali` | Daynum | Notes |
|---|---|---|---|
| Jalali ↔ Gregorian conversion | ✓ | ✓ | Same algorithm (Birashk) |
| Hijri calendar | ✗ | ✓ | UAQ + civil |
| Format tokens | ✓ | ✓ | PHP `date()` syntax |
| Persian digits | ✓ (global) | ✓ (per-view) |  |
| Relative parsing ("tomorrow") | ✓ | ✗ | Use `DateTimeImmutable` |
| Mutable arithmetic | partial | ✗ | Always immutable |
| Timezone math | partial | via escape hatch | `toDateTimeImmutable()` |
| ICU-tested | ✗ | ✓ | ~220k dates/calendar |
| Runtime dependencies | Carbon | none |  |

## See also

- [calendars/jalali.md](calendars/jalali.md) — Birashk details, ICU divergence windows
- [getting-started.md](getting-started.md)
- [cookbook.md](cookbook.md) — Laravel integration patterns
- [faq.md](faq.md)
