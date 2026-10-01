# Timezones

Daynum is a **civil-datetime** library, not a timezone library. The `tzLabel` on a `CivilDateTime` is a passthrough string that travels alongside the date — Daynum never asks your OS for DST offsets or interprets it as a wall-clock anchor. When you need real timezone math, escape to `DateTimeImmutable`.

## What `tzLabel` does and doesn't do

`tzLabel` is the third field of a `CivilDateTime`, alongside `jdn` and `secondsOfDay`. It can be:

- `null` — no timezone attached
- an IANA name — `Asia/Tehran`, `UTC`, `Europe/London`
- a fixed offset — `+03:30`, `-05:00`

Daynum uses `tzLabel` in three places, and three places only:

1. **Formatting**: the `T`, `U`, `O`, `P`, `p`, `Z`, `I`, `c`, `r`, and `e` tokens delegate to PHP's `DateTimeImmutable::format()` with the stored zone.
2. **Interop**: `toDateTimeImmutable()` constructs a `DateTimeImmutable` using the stored zone.
3. **Validation**: constructing a `CivilDateTime` with a syntactically invalid `tzLabel` throws `InvalidTimezoneException`.

Everything else — `equals`, `lessThan`, arithmetic, view reading — ignores the timezone entirely. Two `CivilDateTime` objects with identical `jdn` and `secondsOfDay` but different `tzLabel` values compare equal and format to the same wall-clock time.

```php
$a = CivilDateTime::fromGregorian(2026, 4, 8, 14, 30, 0, 'Asia/Tehran');
$b = CivilDateTime::fromGregorian(2026, 4, 8, 14, 30, 0, 'UTC');

$a->equals($b);             // true — same JDN + same secondsOfDay
$a->gregorian()->format('Y-m-d H:i');  // "2026-04-08 14:30"
$b->gregorian()->format('Y-m-d H:i');  // "2026-04-08 14:30"
// But on the UTC timeline they are 3.5 hours apart.
```

See [concepts.md](concepts.md#civildatetime-is-wall-clock-time).

## When to attach a timezone

Attach a timezone **only** when:

- You need to format with timezone-dependent tokens (`T`, `U`, `O`, `P`, `p`, `Z`, `I`, `c`, `r`, `e`)
- You need to escape to `DateTimeImmutable` later (`toDateTimeImmutable()` uses it)
- You are persisting a value whose wall-clock reading is anchored to a specific civil zone (e.g., Tehran office hours)

If you're doing none of these, leave `tzLabel` as `null` — it makes round-trip less verbose and forces you to think deliberately before asking for timezone-dependent output.

## Attaching after the fact

```php
$d = CivilDateTime::fromJalali(1405, 1, 19, 14, 30);      // no timezone
$d = $d->withTzLabel('Asia/Tehran');                 // returns a new CivilDateTime
$d->jalali()->format('Y/m/d H:i T');                 // now renders the tz
```

`withTzLabel(null)` clears an attached label.

## `MissingTimezoneException`

Using a tz-dependent format token on a `CivilDateTime` without a `tzLabel` throws:

```php
$d = CivilDateTime::fromGregorian(2026, 4, 8, 14, 30);    // no tzLabel
$d->gregorian()->format('Y-m-d H:i:s P');
// → MissingTimezoneException:
// "Format token 'P' requires a timezone, but none is set on this CivilDateTime. ..."
```

Fix: attach a tz with `withTzLabel()`, pass one at construction time, or drop the token.

## The escape hatch: `toDateTimeImmutable()`

For anything that needs real DST arithmetic, UTC conversion, or physical-time comparison:

```php
$d = CivilDateTime::fromGregorian(2026, 4, 8, 14, 30, 0, 'Asia/Tehran');
$native = $d->toDateTimeImmutable();

// Now you have the full DateTimeImmutable API
$native->getTimestamp();               // Unix time
$native->setTimezone(new DateTimeZone('UTC'));
$native->modify('+2 hours');
$native->format(DateTimeInterface::ATOM);
```

The conversion reads the proleptic Gregorian date from the `jdn`, applies the time-of-day, and sets the stored zone. If `tzLabel` is `null`, the resulting `DateTimeImmutable` uses PHP's default timezone.

### Round-tripping

```php
$d = CivilDateTime::fromGregorian(2026, 4, 8, 14, 30, 0, 'Asia/Tehran');
$native = $d->toDateTimeImmutable();
$back = CivilDateTime::fromDateTime($native);
$back->equals($d);     // true
```

## Timestamps

```php
$d = CivilDateTime::fromTimestamp(1775642400, 'Asia/Tehran');   // 2026-04-08 13:30 in Tehran
$d->toTimestamp();                                                // 1775642400

CivilDateTime::fromTimestamp(time());   // tzLabel defaults to 'UTC'
```

`toTimestamp()` needs a timezone and throws `MissingTimezoneException` when `tzLabel` is `null` — without one there is no single moment to convert to.

Two DST cases have no unique answer. Daynum follows PHP for both:

- **Gap** (spring forward): a reading that doesn't exist, like 02:30 on a night that skips from 02:00 to 03:00, moves forward by the length of the gap (03:30).
- **Overlap** (fall back): a reading that happens twice, like 01:30 on a night that repeats 01:00–02:00, resolves to the **earlier** moment.

So `fromTimestamp($t, $tz)->toTimestamp()` returns `$t`, except during the second pass through an overlap, where it returns the earlier moment with the same reading.

## Doing timezone math

`addHours()`, `diffInHours()` and friends are wall-clock: they move or compare the clock-face reading and ignore DST. For exact elapsed time, go through timestamps:

```php
$d = CivilDateTime::fromGregorian(2026, 3, 29, 0, 30, 0, 'Europe/London');   // just before BST

$d->addHours(2)->gregorian()->format('H:i');                                 // "02:30" — wall-clock
CivilDateTime::fromTimestamp($d->toTimestamp() + 7200, 'Europe/London')
    ->gregorian()->format('H:i');                                            // "03:30" — two real hours later

$elapsed = $b->toTimestamp() - $a->toTimestamp();   // exact seconds between two values
```

For anything more involved (`modify('last day of next month')`, recurring events), escape to `DateTimeImmutable` and re-import with `CivilDateTime::fromDateTime()`.

## Converting between zones

```php
$d = CivilDateTime::fromGregorian(2026, 4, 8, 14, 30, 0, 'Asia/Tehran');
$utc = CivilDateTime::fromTimestamp($d->toTimestamp(), 'UTC');
$utc->gregorian()->format('Y-m-d H:i T');   // "2026-04-08 11:00 UTC"
```

## Fixed offsets

`tzLabel` accepts fixed-offset strings: `+03:30`, `-05:00`, `+00:00`. These are passed to `new DateTimeZone()`, which supports them. Use them when you have offset-only data (e.g., a CSV column that stores `+03:30` but not an IANA name) and don't need DST handling.

```php
$d = CivilDateTime::fromGregorian(2026, 4, 8, 14, 30, 0, '+03:30');
$d->gregorian()->format('c');   // "2026-04-08T14:30:00+03:30"
```

## Common pitfall: comparing across timezones

`equals()`, `lessThan()` and `compare()` **do not** translate across zones. If you have two `CivilDateTime` values and want to know which represents the earlier physical moment, compare timestamps:

```php
$a = CivilDateTime::fromGregorian(2026, 4, 8, 14, 30, 0, 'Asia/Tehran');
$b = CivilDateTime::fromGregorian(2026, 4, 8, 14, 30, 0, 'UTC');

$a->equals($b);                          // true — wall-clock equality
$a->toTimestamp() === $b->toTimestamp(); // false
$a->toTimestamp() < $b->toTimestamp();   // true (Tehran 14:30 is 11:00 UTC)
```

See [faq.md](faq.md#why-isnt-equals-timezone-aware) for the rationale.

## See also

- [concepts.md](concepts.md#civildatetime-is-wall-clock-time) — civil vs. UTC
- [serialization.md](serialization.md) — how `tzLabel` round-trips through JSON
- [exceptions.md](exceptions.md#missingtimezoneexception) — `MissingTimezoneException`
- [cookbook.md](cookbook.md#do-timezone-math-by-escape-hatching-to-datetimeimmutable) — worked example
