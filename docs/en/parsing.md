# Parsing

Daynum parses strict format strings — no relative dates (`"next Monday"`), no fuzzy input, no locale-name matching. The parser is a mirror of the formatter: same tokens, same grammar, strict literal matching.

## `parseExact` and `tryParseExact`

Both are **static** methods on each calendar view:

```php
use Daynum\Calendar\Gregorian\GregorianView;
use Daynum\Calendar\Jalali\JalaliView;
use Daynum\Calendar\Hijri\HijriUmmAlQuraView;
use Daynum\Calendar\Hijri\HijriCivilView;

GregorianView::parseExact('2026-04-08', 'Y-m-d');
JalaliView::parseExact('1405/01/19', 'Y/m/d');
HijriUmmAlQuraView::parseExact('1447/10/21', 'Y/m/d');
HijriCivilView::parseExact('0001/1/1', 'Y/n/j');    // Y still requires 4+ digits

// Safe variant — returns null instead of throwing
JalaliView::tryParseExact('not-a-date', 'Y/m/d');   // null
```

Both return an `Instant` (not a view) on success. On failure, `parseExact` throws `ParseException`, `tryParseExact` returns `null`.

## Parseable tokens

| Token | Field | Width |
|-------|-------|-------|
| `Y` | year | 4+ digits (exactly 4 if followed by another token with no separator) |
| `m` | month | exactly 2 digits |
| `n` | month | 1–2 digits (greedy; requires a trailing literal) |
| `d` | day | exactly 2 digits |
| `j` | day | 1–2 digits (greedy; requires a trailing literal) |
| `H` | hour (24h) | exactly 2 digits |
| `G` | hour (24h) | 1–2 digits (greedy) |
| `h` | hour (12h) | exactly 2 digits — **requires `a`/`A`** |
| `g` | hour (12h) | 1–2 digits (greedy) — **requires `a`/`A`** |
| `i` | minute | exactly 2 digits |
| `s` | second | exactly 2 digits |
| `a` / `A` | meridiem | `am`/`pm`/`AM`/`PM`, or `ق.ظ`/`ب.ظ` (fa), or `ص`/`م` (ar) |
| `P` / `p` | tz offset | `+HH:MM` or `Z` |
| `O` | tz offset | `+HHMM` |
| `c` | ISO 8601 composite | expands to `Y-m-d\TH:i:sP` |

### Format-only tokens (cannot be parsed)

`y`, `z`, `D`, `l`, `F`, `M`, `N`, `w`, `W`, `o`, `t`, `L`, `S`, `T`, `e`, `U`, `Z`, `I`, `r`, `u`, `v`.

Locale-dependent tokens (`F`, `M`, `l`, `D`) are format-only because Daynum does not ship a locale-aware name-matcher — it would add complexity and ambiguity without improving the common case.

## The variable-width rule

Variable-width tokens (`n`, `j`, `G`, `g`) grab 1–2 digits greedily. They **must** be followed by a literal separator, never another token — otherwise the parser cannot tell where one field ends and the next begins:

```php
GregorianView::parseExact('2026-4-8', 'Y-n-j');     // works — dashes are separators
GregorianView::parseExact('202648',   'Ynj');       // throws — ambiguous
```

## The `h` / `a` rule

`h` and `g` (12-hour) require a companion `a` or `A` token in the same format — otherwise `"01"` is ambiguous (1 AM or 1 PM?).

```php
GregorianView::parseExact('2026-04-08 02:30 PM', 'Y-m-d h:i A');   // works
GregorianView::parseExact('2026-04-08 02:30', 'Y-m-d h:i');         // throws
```

## Digit normalization

Digits in any script are normalized to ASCII before parsing:

```php
JalaliView::parseExact('۱۴۰۵/۰۱/۱۹', 'Y/m/d');    // works — Persian digits (U+06F0)
JalaliView::parseExact('١٤٠٥/٠١/١٩', 'Y/m/d');    // works — Arabic-Indic digits (U+0660)
```

You do not need `withDigits()` on the parser — the transliteration is automatic.

## Meridiem indicators

The `a` / `A` tokens accept:

- English: `am`, `pm`, `AM`, `PM` (case-insensitive)
- Persian: `ق.ظ` (AM), `ب.ظ` (PM)
- Arabic: `ص` (AM), `م` (PM)

```php
JalaliView::parseExact('1405/01/19 02:30 ب.ظ', 'Y/m/d h:i A');
```

## Timezone offsets

```php
GregorianView::parseExact('2026-04-08T14:30:00+03:30', 'c');           // composite
GregorianView::parseExact('2026-04-08 14:30 +03:30',    'Y-m-d H:i P');
GregorianView::parseExact('2026-04-08 14:30 Z',         'Y-m-d H:i P');
GregorianView::parseExact('2026-04-08 14:30 +0330',     'Y-m-d H:i O');
```

A parsed `P`/`p`/`O` offset **overrides** any `$tzLabel` parameter passed to `parseExact`. Offsets are validated against the real-world IANA range (`-12:00` to `+14:00`).

## Required fields

A format must include at least `Y`, one of `m`/`n`, and one of `d`/`j`. Hours, minutes, and seconds default to `0`. If you parse without time tokens, the resulting `Instant` has `secondsOfDay == 0`.

```php
GregorianView::parseExact('2026-04-08', 'Y-m-d')
    ->gregorian()
    ->format('Y-m-d H:i:s');    // "2026-04-08 00:00:00"
```

## Error modes

`ParseException` is thrown for:

- Format / input mismatch (literal doesn't match, wrong number of digits)
- Unsupported token in the format string
- Trailing input after the last token
- Out-of-range time components (`hour > 23`, etc.)
- 12-hour / meridiem ambiguity
- Underlying calendar validation failure (invalid month, day, out of range)

`parseExact` wraps all calendar-level exceptions (`InvalidDateException`, `UmmAlQuraOutOfRangeException`) in `ParseException`, so `tryParseExact` catches them all uniformly.

```php
try {
    JalaliView::parseExact('1405/13/01', 'Y/m/d');
} catch (\Daynum\Exception\ParseException $e) {
    // "Cannot parse '1405/13/01' with format 'Y/m/d': month must be in [1, 12]"
}
```

## See also

- [formatting.md](formatting.md) — the token table and formatter semantics
- [localization.md](localization.md) — digit scripts and locales
- [exceptions.md](exceptions.md#parseexception)
