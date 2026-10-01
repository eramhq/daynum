# Localization

Daynum ships four locales — `en`, `fa`, `fa-AF` (Dari), `ar` — and three digit scripts — `latn`, `persian`, `arab`. Locales and digit scripts are independent dimensions: you can format in Persian with ASCII digits, or in English with Arabic-Indic digits.

## Switching locale

```php
$d = CivilDateTime::fromJalali(1405, 1, 19);

$d->jalali()->format('l j F Y');                      // "Wednesday 19 Farvardin 1405"
$d->jalali()->withLocale('fa')->format('l j F Y');    // "چهارشنبه 19 فروردین 1405"
$d->jalali()->withLocale('ar')->format('j F Y');      // throws on the `F` token
```

`withLocale()` returns a new view. Tags are case-insensitive and fall back from region to language (`en-US` → `en`, `fa-IR` → `fa`, `ar-SA` → `ar`); `fa-AF` is its own locale. Unknown languages throw `InvalidArgumentException` — no silent fallback to English. Add your own with [`LocaleRegistry::register()`](#custom-locales).

## Switching digit script

```php
$d->jalali()->withDigits('persian')->format('Y/m/d');  // "۱۴۰۵/۰۱/۱۹"
$d->hijri()->withDigits('arab')->format('Y/m/d');      // "١٤٤٧/١٠/٢٠"
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
| First day of week | Monday | Saturday | Sunday |
| Weekend | Saturday, Sunday | Friday | Friday, Saturday |
| Relative time | `3 days ago`, `in 3 days`, `now` | `3 روز پیش`, `3 روز دیگر`, `اکنون` | `قبل 3 أيام`, `خلال 3 أيام`, `الآن` |
| Season names | `Spring` … `Winter` | `بهار`, `تابستان`, `پاییز`, `زمستان` | `الربيع`, `الصيف`, `الخريف`, `الشتاء` |

Persian and Arabic have no traditional weekday abbreviations or ordinal suffixes, so `D` emits the same string as `l`, and `S` emits an empty string. This matches ICU's behavior and keeps patterns like `jS F Y` from leaving broken `th` residue inside Perso-Arabic text.

Dari (`fa-AF`) is identical to `fa` except for its Jalali month names (`حمل`, `ثور`, `جوزا`, …), its Gregorian month names (`جنوری`, `فبروری`, …), a Thursday–Friday weekend, and `خزان` for autumn.

The week rows drive `startOfWeek()` / `endOfWeek()` with no argument and `isWeekend()`. See [arithmetic.md](arithmetic.md#weeks).

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

Pass a locale tag as `parseExact`'s fourth argument to parse month and weekday names. Digit scripts are normalized automatically, whatever the locale:

```php
JalaliView::parseExact('۱۹ فروردین ۱۴۰۵', 'j F Y', null, 'fa');
JalaliView::parseExact('١٤٠٥/٠١/١٩', 'Y/m/d');  // Arabic-Indic digits
```

See [parsing.md](parsing.md#month-and-weekday-names).

## Custom locales

`LocaleRegistry::register()` adds a locale, or replaces a built-in one. Do it once at boot (for example in a Laravel service provider):

```php
use Eram\Daynum\Locale\EnglishLocale;
use Eram\Daynum\Locale\LocaleRegistry;
use Eram\Daynum\WeekDay;

// US English: Sunday-start weeks, everything else as `en`.
final class UsEnglishLocale extends EnglishLocale
{
    public function tag(): string
    {
        return 'en-US';
    }

    public function firstDayOfWeek(): WeekDay
    {
        return WeekDay::Sunday;
    }
}

LocaleRegistry::register('en-US', new UsEnglishLocale());

$d->gregorian()->withLocale('en-US')->startOfWeek();   // Sunday
```

Tags are case-insensitive, `_` equals `-`, and a regional tag falls back to its language: `withLocale('en-GB')` uses `en` unless `en-GB` is registered. That is how `fa-IR` and `ar-SA` resolve. Registering an existing tag replaces it, so you can also override `en`, `fa` or `ar` wholesale.

### Extending a built-in locale

`EnglishLocale`, `PersianLocale` and `ArabicLocale` are open for extension. Their name tables are protected methods, keyed by the calendar's locale family (`gregorian`, `jalali`, `hijri`) and then by 1-based month:

```php
protected function longMonthTable(): array;      // F
protected function shortMonthTable(): array;     // M
protected function longWeekdayTable(): array;    // l, indexed Sunday = 0
protected function shortWeekdayTable(): array;   // D
```

The shipped Dari locale (`fa-AF`) is built this way: [`DariLocale`](../../src/Locale/DariLocale.php) extends `PersianLocale`, swaps in the Afghan Jalali month names (حمل, ثور, جوزا, …) and Gregorian spellings (جنوری, فبروری, …), and moves the weekend to Thursday–Friday.

### Writing a locale from scratch

Extend `AbstractTableLocale` and implement the four tables plus `tag()`, `meridiem()`, `ordinalSuffix()`, `firstDayOfWeek()`, `weekendDays()`, `relativeTime()`, `relativeTimeNow()` and `seasonName()`. Leave a calendar family out of the month tables if your locale has no names for it; formatting `F`/`M` in that calendar then throws, as Arabic does for Jalali. Call `self::assertRelativeTimeArgs($value, $unit)` at the top of `relativeTime()` to get the standard argument checks.

Or implement the `LocaleData` interface directly if your data isn't table-shaped.

## See also

- [formatting.md](formatting.md) — token reference
- [parsing.md](parsing.md) — which tokens parse and how digits normalize
- [calendars/jalali.md](calendars/jalali.md) — Jalali month names per locale
- [faq.md](faq.md) — why no Arabic Jalali, how locale-aware parsing is
