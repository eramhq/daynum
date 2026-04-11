# Localization

v1 ships three locales — `en`, `fa`, `ar` — and three digit scripts — `latn`, `persian`, `arab`. Locales and digit scripts are independent dimensions: you can format in Persian with ASCII digits, or in English with Arabic-Indic digits.

## Switching locale

```php
$d = Instant::fromJalali(1405, 1, 19);

$d->jalali()->format('l j F Y');                      // "Wednesday 19 Farvardin 1405"
$d->jalali()->withLocale('fa')->format('l j F Y');    // "چهارشنبه 19 فروردین 1405"
$d->jalali()->withLocale('ar')->format('j F Y');      // throws on the `F` token
```

`withLocale()` returns a new view. Accepted tags: `en`, `en-us`, `fa`, `fa-ir`, `ar`, `ar-sa` (case-insensitive). Unknown tags throw `InvalidArgumentException` — no silent fallback.

## Switching digit script

```php
$d->jalali()->withDigits('persian')->format('Y/m/d');  // "۱۴۰۵/۰۱/۱۹"
$d->hijri()->withDigits('arab')->format('Y/m/d');      // "١٤٤٧/١٠/٢١"
$d->gregorian()->withDigits('latn')->format('Y-m-d');  // "2026-04-08"
```

`persian` uses Unicode `U+06F0..06F9` (۰-۹). `arab` uses `U+0660..0669` (٠-٩). **They are distinct scripts** — Persian sites expect `U+06F0`, Arabic sites expect `U+0660`. Using the wrong one is a common bug; `withDigits('persian')` on an Arabic page won't look right to the reader.

## Combining locale + digits

```php
$d->jalali()
  ->withLocale('fa')
  ->withDigits('persian')
  ->format('l j F Y');
// "چهارشنبه ۱۹ فروردین ۱۴۰۵"
```

Order doesn't matter — both methods return a fresh view, and they compose freely.

## What each locale provides

| | English (`en`) | Persian (`fa`) | Arabic (`ar`) |
|---|---|---|---|
| Gregorian month names | `January`, `February`, … | `ژانویهٔ`, `فوریهٔ`, … | `يناير`, `فبراير`, … |
| Jalali month names | `Farvardin`, `Ordibehesht`, … | `فروردین`, `اردیبهشت`, … | **throws** |
| Hijri month names | `Muharram`, `Safar`, … | `محرم`, `صفر`, … | `محرم`, `صفر`, … |
| Weekday names | `Sunday`, `Monday`, … | `یکشنبه`, `دوشنبه`, … | `الأحد`, `الاثنين`, … |
| Short weekdays | `Sun`, `Mon`, … | (same as long) | (same as long) |
| Meridiem | `am`/`pm` / `AM`/`PM` | `ق.ظ` / `ب.ظ` | `ص` / `م` |
| Ordinal suffix (`S`) | `st`, `nd`, `rd`, `th` | `""` (empty) | `""` (empty) |

Persian and Arabic have no traditional weekday abbreviations or ordinal suffixes, so `D` emits the same string as `l`, and `S` emits an empty string. This matches ICU's behavior and keeps patterns like `jS F Y` from leaving broken `th` residue inside Perso-Arabic text.

## The Arabic + Jalali limitation

The Arabic locale does **not** define Jalali month names. Calling a Jalali `F` or `M` token under the Arabic locale throws.

```php
$d->jalali()->withLocale('ar')->format('j F Y');
// → throws: "Arabic locale does not define Jalali month names. ..."
```

This is deliberate. ICU's Arabic transliterations of Persian month names are low-quality phonetic approximations (e.g., `فرفردن` for Farvardin) that no modern Arabic-speaking audience actually reads. Rather than shipping low-quality data, Daynum throws and directs you to a better option.

**Recommended:** use `withLocale('fa')` when displaying Jalali to an Arabic-script audience. Persian month names render in the same Perso-Arabic script and are universally recognized:

```php
$d->jalali()->withLocale('fa')->format('j F Y');
// "19 فروردین 1405" — readable to both Persian and Arabic speakers
```

Gregorian and Hijri are unaffected — the Arabic locale ships full tables for both.

## Default locale

Views are constructed with the `en` locale by default. Set it explicitly with `withLocale('fa')` — there is no global default setting. If you need every view in your application to use `fa`, wrap the view construction in a helper.

## Persian ezafe on Gregorian months

The Persian long-form Gregorian month table emits the Persian *ezafe* hamzeh (U+0654) on names whose Persian spelling ends in a vowel — `ژانویهٔ`, `فوریهٔ`, `مهٔ`, `ژوئیهٔ`. This matches ICU's `MMMM` output for `fa` and reflects the grammatical "of" construction used when a day number follows the month. Short form drops the ezafe (`ژانویه`, `فوریه`, …).

## Timezone token output is not transliterated

Output from `T`, `U`, `O`, `P`, `p`, `Z`, `I`, `c`, and `r` tokens stays in ASCII regardless of the active digit script. These are programmatic interchange formats, not display text — you do not want a JSON consumer to see `۲۰۲۶-۰۴-۰۸T۱۴:۳۰:۰۰+۰۳:۳۰` in an `"timestamp"` field.

```php
$d->jalali()->withDigits('persian')->format('Y/m/d H:i P');
// "۱۴۰۵/۰۱/۱۹ ۱۴:۳۰ +03:30"
// ------- display digits ------   ASCII offset
```

## Parsing with locale

Parsing is **not** locale-aware — locale tokens (`F`, `M`, `l`, `D`) can't be parsed. Digit scripts are normalized automatically, so Persian or Arabic-Indic digits always parse correctly regardless of the calling view:

```php
JalaliView::parseExact('۱۴۰۵/۰۱/۱۹', 'Y/m/d');  // works — Persian digits
JalaliView::parseExact('١٤٠٥/٠١/١٩', 'Y/m/d');  // works — Arabic-Indic digits
```

See [parsing.md](parsing.md).

## See also

- [formatting.md](formatting.md) — token reference
- [parsing.md](parsing.md) — which tokens parse and how digits normalize
- [calendars/jalali.md](calendars/jalali.md) — Jalali month names per locale
- [faq.md](faq.md) — why no Arabic Jalali, why no locale-aware parsing
