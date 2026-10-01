# Parsing

Daynum parses strict format strings — no relative dates (`"next Monday"`), no fuzzy input. The parser is a mirror of the formatter: same tokens, same grammar, strict literal matching, and month/weekday names in a locale you choose.

## `parseExact` and `tryParseExact`

Both are **static** methods on each calendar view:

```php
use Eram\Daynum\Calendar\Gregorian\GregorianView;
use Eram\Daynum\Calendar\Jalali\JalaliView;
use Eram\Daynum\Calendar\Hijri\HijriUmmAlQuraView;
use Eram\Daynum\Calendar\Hijri\HijriCivilView;

GregorianView::parseExact('2026-04-08', 'Y-m-d');
JalaliView::parseExact('1405/01/19', 'Y/m/d');
HijriUmmAlQuraView::parseExact('1447/10/20', 'Y/m/d');
HijriCivilView::parseExact('0001/1/1', 'Y/n/j');    // Y still requires 4+ digits

// Month and weekday names, in the locale passed as the 4th argument
JalaliView::parseExact('چهارشنبه ۱۹ فروردین ۱۴۰۵', 'l j F Y', null, 'fa');
GregorianView::parseExact('Wed, 8 Apr 2026', 'D, j M Y');            // locale defaults to 'en'

// Safe variant — returns null instead of throwing
JalaliView::tryParseExact('not-a-date', 'Y/m/d');   // null
```

The full signature is `parseExact(string $text, string $format, ?string $tzLabel = null, ?string $locale = null)`.

Both return a `CivilDateTime` (not a view) on success. On failure, `parseExact` throws `ParseException`, `tryParseExact` returns `null`.

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
| `a` / `A` | meridiem | the parse locale's AM/PM marker, or `am`/`pm`, `ق.ظ`/`ب.ظ`, `ص`/`م` in any locale |
| `P` / `p` | tz offset | `+HH:MM` or `Z` |
| `O` | tz offset | `+HHMM` |
| `c` | ISO 8601 composite | expands to `Y-m-d\TH:i:sP` |
| `F` / `M` | month | month name, full **or** short, in the parse locale |
| `l` / `D` | weekday | weekday name, full or short; must match the parsed date |

### Format-only tokens (cannot be parsed)

`y`, `z`, `N`, `w`, `W`, `o`, `t`, `L`, `S`, `T`, `e`, `U`, `Z`, `I`, `r`, `u`, `v`.

## Month and weekday names

`F`, `M`, `l` and `D` match the names of the locale passed as `parseExact`'s fourth argument (default `en`), for the view's calendar: `JalaliView` matches Jalali month names, `HijriCivilView` Hijri ones.

```php
JalaliView::parseExact('19 Farvardin 1405', 'j F Y');                    // en
JalaliView::parseExact('۱۹ فروردین ۱۴۰۵', 'j F Y', null, 'fa');           // fa
JalaliView::parseExact('3 سنبله 1405', 'j F Y', null, 'fa-AF');           // Dari month names
HijriUmmAlQuraView::parseExact('20 شوال 1447', 'j F Y', null, 'ar');
```

Matching is forgiving where keyboards and fonts differ, and strict everywhere else:

- `F` and `M` both accept the full and the short name (`April` or `Apr`); `l` and `D` likewise. The longest name wins, so `June` is never read as `Jun` + `e`.
- A name must end at a word boundary: `Aprl` fails rather than matching `Apr`.
- Latin letters are case-insensitive (`APRIL`, `april`), including Latin-1 and Turkish letters (`ŞEVVAL`, `şevval`). Every Turkish I (`İ`, `I`, `ı`) matches every other, so the dotted/dotless difference never blocks a match.
- Arabic `ي`, `ى` and `ك` match Persian `ی` and `ک`, so text typed on an Arabic keyboard parses as Persian.
- The zero-width non-joiner (ZWNJ) and the ezafe hamza (`ٔ`) are optional: `سهشنبه` matches `سه‌شنبه`, and `ژانویه` matches `ژانویهٔ`.
- A space is not a ZWNJ: `سه شنبه` does not match.
- A weekday must agree with the date: `Monday 8 April 2026` throws, because 8 April 2026 is a Wednesday.
- Two tokens for the same field must agree: `F Y-m-d` throws if the name and the number name different months.

Calendars without names in the locale throw `ParseException` — the Arabic locale has no Jalali month names. An unknown locale tag throws `InvalidArgumentException`, since it is a programming error rather than bad input.

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

The `a` / `A` tokens accept the parse locale's own markers (from `LocaleData::meridiem()`, in both forms) plus these, whatever the locale:

- `am`, `pm`
- Persian: `ق.ظ` (AM), `ب.ظ` (PM)
- Arabic: `ص` (AM), `م` (PM)

Markers match like names: case-insensitive, longest first, and ending at a word boundary (`pmx` is not `pm`).

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

A format must include at least `Y`, one of `m`/`n`, and one of `d`/`j`. Hours, minutes, and seconds default to `0`. If you parse without time tokens, the resulting `CivilDateTime` has `secondsOfDay == 0`.

```php
GregorianView::parseExact('2026-04-08', 'Y-m-d')
    ->gregorian()
    ->format('Y-m-d H:i:s');    // "2026-04-08 00:00:00"
```

## Error modes

`ParseException` is thrown for:

- Format / input mismatch (literal doesn't match, wrong number of digits)
- Unsupported token in the format string
- Unknown month or weekday name, or a weekday that doesn't match the date
- Two tokens giving different values for the same field
- Trailing input after the last token
- Out-of-range time components (`hour > 23`, etc.)
- 12-hour / meridiem ambiguity
- Underlying calendar validation failure (invalid month, day, out of range)

`parseExact` wraps all calendar-level exceptions (`InvalidDateException`, `UmmAlQuraOutOfRangeException`) in `ParseException`, so `tryParseExact` catches them all uniformly.

```php
try {
    JalaliView::parseExact('1405/13/01', 'Y/m/d');
} catch (\Eram\Daynum\Exception\ParseException $e) {
    // "Cannot parse '1405/13/01' with format 'Y/m/d': month must be in [1, 12]"
}
```

## See also

- [formatting.md](formatting.md) — the token table and formatter semantics
- [localization.md](localization.md) — digit scripts and locales
- [exceptions.md](exceptions.md#parseexception)
