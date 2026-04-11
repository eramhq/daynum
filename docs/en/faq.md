# FAQ

Surprising-but-intentional decisions, gotchas, and the reasoning behind them.

## Why is `Instant` civil, not UTC?

Daynum is a multi-calendar library. Its currency is **wall-clock dates**: "Farvardin 19, 1405" or "21 Shawwal 1447". These are civil concepts — they don't refer to points on the UTC timeline until you pick a timezone.

If `Instant` were UTC, then every calendar operation would need to resolve a timezone first, and every comparison would need a DST table. That's exactly what `DateTimeImmutable` already does, and Daynum has no business re-implementing it.

Instead, `Instant` stores `(JDN, time-of-day, timezone label)`. Two `Instant`s with the same JDN and time — regardless of stored zone — compare equal and format to the same string. When you need physical-time math, escape to `DateTimeImmutable` with `$d->toDateTimeImmutable()`. See [concepts.md](concepts.md#instant-is-civil-not-utc).

## Why isn't `equals()` timezone-aware?

Because the whole type is civil. Making `equals()` timezone-aware would create an asymmetry: `format()` ignores the zone, `equals()` respects it, and you'd get bugs where two equal displays compare unequal.

If you want physical-time comparison, convert both sides:

```php
$a->toDateTimeImmutable() == $b->toDateTimeImmutable();
```

## Why no relative date parsing ("next Monday", "+2 weeks")?

Two reasons:

1. **Ambiguity.** What does "next Monday" mean on a Monday? Every library answers differently, and the answer most users expect depends on region and context.
2. **Scope.** Daynum is a multi-calendar library, not a natural-language date parser. Supporting relative dates would force us to ship different expressions per locale, which is a rabbit hole.

If you need it, use PHP's `strtotime()` or the `DateTimeImmutable` constructor, then import the result:

```php
$d = Instant::fromDateTime(new DateTimeImmutable('next Monday'));
```

## Why no Arabic Jalali month names?

ICU's Arabic transliterations of Persian month names are low-quality phonetic approximations (e.g., `فرفردن` for Farvardin) that no modern Arabic-speaking audience actually reads. Shipping them would be shipping bad data.

The Arabic locale intentionally omits Jalali month tables, so `$d->jalali()->withLocale('ar')->format('F')` throws. If you're rendering Jalali to an Arabic-script audience, use `withLocale('fa')` — Persian month names render in the same Perso-Arabic script and are universally recognized.

See [localization.md](localization.md#the-arabic--jalali-limitation).

## Why Birashk, not Borkowski (ICU's Persian calendar)?

Daynum prioritizes compatibility with the dominant PHP/JS Jalali ecosystem — `morilog/jalali`, `jalaali-js`, `date-fns-jalali` — over strict ICU conformance. Every migrating developer already tests against Birashk output; switching to Borkowski would break those tests without improving anything for the target audience.

Across the 1700–2300 Gregorian window, Birashk and ICU's `persian` agree on 99.5% of days. The 0.5% where they disagree forms four contiguous windows, documented in [calendars/jalali.md](calendars/jalali.md#jalali-and-icu-a-note-on-correctness).

## Why does Hijri Umm al-Qura throw instead of returning a civil fallback?

Outside the bundled table range (AH 1300–1600), ICU silently falls back to the arithmetic civil calendar. That means an application displaying AH 1250 through ICU gets `islamic-civil` output mislabeled as UAQ — exactly the kind of quiet credibility hazard Daynum exists to prevent.

So Daynum throws a loud `UmmAlQuraOutOfRangeException` and directs you to `fromHijriCivil()`. The user decides what calendar to fall back to — Daynum never guesses. See [calendars/hijri-umm-al-qura.md](calendars/hijri-umm-al-qura.md#why-throw-instead-of-silently-falling-back).

## Why do I have to re-enter a view after arithmetic?

Because arithmetic returns `Instant`, not another view:

```php
$next = $d->jalali()->addMonths(1);   // Instant, not JalaliView
$next->jalali()->format('Y/m/d');     // re-enter Jalali view
```

`Instant` is calendar-neutral. If `addMonths` returned a view, we'd need one of:

- Glue the last-used calendar onto the `Instant` (breaks calendar-neutrality, bad for storage)
- Make the arithmetic mutate the view in place (not immutable anymore)
- Return a view with an implicit "current" calendar (confusing when you mix calendars)

Re-entering the view is one extra method call and keeps the model clean. See [concepts.md](concepts.md#instant-vs-view).

## Why don't you ship `islamic-tbla` or observational `islamic`?

- `islamic-tbla` is the same arithmetic calendar as `islamic-civil` but with a Thursday epoch (JDN 1948439) instead of Friday (JDN 1948440). A rare regional variant. Could ship later if users ask.
- Observational `islamic` depends on astronomical new-moon visibility, which is **non-deterministic** — different observers in different locations see the new moon on different nights. Pretending to compute it from a closed-form formula would be a lie, so Daynum will never ship it.

See [calendars/hijri-civil.md](calendars/hijri-civil.md#why-not-other-islamic-variants).

## Why isn't parsing locale-aware?

`parseExact` is strict: it doesn't try to match month names or weekday names, even though the formatter writes them. Locale-aware parsing is a can of worms — "Farvardin" vs. "فروردین" vs. "farvardin" vs. "FARVARDIN" vs. "fâr" vs. "far" — with no clean answer.

For structured input, use numeric tokens (`Y m d`) which parse unambiguously. For free-form input, fall back to `DateTimeImmutable` + `Instant::fromDateTime()`, then validate.

See [parsing.md](parsing.md).

## Why does `diffInDays` live on `Instant` but `diffInMonths` on the view?

A "day" is calendar-neutral — `diffInDays` is just a JDN subtraction. It belongs on `Instant`.

A "month" is calendar-specific — Jalali months and Gregorian months have different lengths, and the diff in Jalali months between two dates is not the same as the diff in Gregorian months. So `diffInMonths` must live on a view that picks a calendar.

```php
$a->diffInDays($b);                  // on Instant
$a->jalali()->diffInMonths($b);      // on the view
$a->gregorian()->diffInMonths($b);   // potentially different answer
```

## Why `1`-based months?

Because 0-based months are the single most common source of off-by-one bugs in date code, and Daynum's audience has been typing `$month = 4` for years. `January = 1`. Always. Explicitly rejecting ICU's 0-based trap is a feature.

## Why no sub-second precision?

- The multi-calendar audience never asks for it.
- Sub-second math introduces float/int precision choices that distract from the calendar math, which is Daynum's actual job.
- If you need microseconds, escape to `DateTimeImmutable`.

## Why does `c` always render Gregorian, even from a Jalali view?

`c` is ISO 8601, which is defined in the Gregorian calendar. A Jalali ISO 8601 string would confuse every downstream consumer. The `c` and `r` tokens exist for interchange with other systems — they always use Gregorian, regardless of the calling view. See [formatting.md](formatting.md#composite-c-token).

## Is Daynum a Carbon replacement?

No. Carbon covers Gregorian + rich timezone arithmetic + relative parsing; Daynum covers multi-calendar + correctness + a small deterministic API. They solve different problems. You can use both in the same project — `Instant::fromDateTime(Carbon::parse(...))` is a clean bridge.

Daynum is a replacement for `morilog/jalali`.

## Why no ORM integration / Laravel package?

It's on the post-v1 roadmap. Daynum v1 keeps `daynum/laravel` out of scope on purpose — the core library should be tight, tested, and tiny before we ship integrations. If demand appears, a separate `daynum/laravel` package can land without forcing a dependency on Illuminate into the core.

Framework integration code stubs are in [cookbook.md](cookbook.md#use-daynum-in-a-laravel-requestresponse).

## See also

- [concepts.md](concepts.md) — the model behind every "why" answer above
- [cookbook.md](cookbook.md) — how to actually do things
- [migration-from-morilog-jalali.md](migration-from-morilog-jalali.md)
