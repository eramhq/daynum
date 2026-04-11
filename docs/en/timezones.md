# Timezones

Daynum is a **civil-datetime** library, not a timezone library. The `tzLabel` on an `Instant` is a passthrough string that travels alongside the date — Daynum never asks your OS for DST offsets or interprets it as a wall-clock anchor. When you need real timezone math, escape to `DateTimeImmutable`.

## What `tzLabel` does and doesn't do

`tzLabel` is the third field of an `Instant`, alongside `jdn` and `secondsOfDay`. It can be:

- `null` — no timezone attached
- an IANA name — `Asia/Tehran`, `UTC`, `Europe/London`
- a fixed offset — `+03:30`, `-05:00`

Daynum uses `tzLabel` in three places, and three places only:

1. **Formatting**: the `T`, `U`, `O`, `P`, `p`, `Z`, `I`, `c`, `r`, and `e` tokens delegate to PHP's `DateTimeImmutable::format()` with the stored zone.
2. **Interop**: `toDateTimeImmutable()` constructs a `DateTimeImmutable` using the stored zone.
3. **Validation**: constructing an `Instant` with a syntactically invalid `tzLabel` throws `InvalidTimezoneException`.

Everything else — `equals`, `lessThan`, arithmetic, view reading — ignores the timezone entirely. Two `Instant` objects with identical `jdn` and `secondsOfDay` but different `tzLabel` values compare equal and format to the same wall-clock time.

```php
$a = Instant::fromGregorian(2026, 4, 8, 14, 30, 0, 'Asia/Tehran');
$b = Instant::fromGregorian(2026, 4, 8, 14, 30, 0, 'UTC');

$a->equals($b);             // true — same JDN + same secondsOfDay
$a->gregorian()->format('Y-m-d H:i');  // "2026-04-08 14:30"
$b->gregorian()->format('Y-m-d H:i');  // "2026-04-08 14:30"
// But on the UTC timeline they are 3.5 hours apart.
```

See [concepts.md](concepts.md#instant-is-civil-not-utc).

## When to attach a timezone

Attach a timezone **only** when:

- You need to format with timezone-dependent tokens (`T`, `U`, `O`, `P`, `p`, `Z`, `I`, `c`, `r`, `e`)
- You need to escape to `DateTimeImmutable` later (`toDateTimeImmutable()` uses it)
- You are persisting a value whose wall-clock reading is anchored to a specific civil zone (e.g., Tehran office hours)

If you're doing none of these, leave `tzLabel` as `null` — it makes round-trip less verbose and forces you to think deliberately before asking for timezone-dependent output.

## Attaching after the fact

```php
$d = Instant::fromJalali(1405, 1, 19, 14, 30);      // no timezone
$d = $d->withTzLabel('Asia/Tehran');                 // returns a new Instant
$d->jalali()->format('Y/m/d H:i T');                 // now renders the tz
```

`withTzLabel(null)` clears an attached label.

## `MissingTimezoneException`

Using a tz-dependent format token on an `Instant` without a `tzLabel` throws:

```php
$d = Instant::fromGregorian(2026, 4, 8, 14, 30);    // no tzLabel
$d->gregorian()->format('Y-m-d H:i:s P');
// → MissingTimezoneException:
// "Format token 'P' requires a timezone, but none is set on this Instant. ..."
```

Fix: attach a tz with `withTzLabel()`, pass one at construction time, or drop the token.

## The escape hatch: `toDateTimeImmutable()`

For anything that needs real DST arithmetic, UTC conversion, or physical-time comparison:

```php
$d = Instant::fromGregorian(2026, 4, 8, 14, 30, 0, 'Asia/Tehran');
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
$d = Instant::fromGregorian(2026, 4, 8, 14, 30, 0, 'Asia/Tehran');
$native = $d->toDateTimeImmutable();
$back = Instant::fromDateTime($native);
$back->equals($d);     // true
```

## Doing timezone math

Daynum deliberately does not offer DST-aware arithmetic. Instead, escape, compute, and re-import:

```php
function addHoursAcrossDst(Instant $d, int $hours): Instant
{
    $native = $d->toDateTimeImmutable()->modify("+{$hours} hours");
    return Instant::fromDateTime($native);
}

$d = Instant::fromGregorian(2026, 3, 29, 1, 30, 0, 'Europe/London');  // just before BST
$d2 = addHoursAcrossDst($d, 1);
$d2->gregorian()->format('Y-m-d H:i T');   // "2026-03-29 03:30 BST"
```

The civil-time hop from 01:30 to 03:30 (skipping 02:30, which doesn't exist) is handled by PHP, not by Daynum.

## Converting between zones

```php
$d = Instant::fromGregorian(2026, 4, 8, 14, 30, 0, 'Asia/Tehran');
$utc = Instant::fromDateTime(
    $d->toDateTimeImmutable()->setTimezone(new DateTimeZone('UTC'))
);
$utc->gregorian()->format('Y-m-d H:i T');   // "2026-04-08 11:00 UTC"
```

Again — this works because the conversion goes through native PHP. Daynum's `Instant` just carries the result back into the calendar-aware world.

## Fixed offsets

`tzLabel` accepts fixed-offset strings: `+03:30`, `-05:00`, `+00:00`. These are passed to `new DateTimeZone()`, which supports them. Use them when you have offset-only data (e.g., a CSV column that stores `+03:30` but not an IANA name) and don't need DST handling.

```php
$d = Instant::fromGregorian(2026, 4, 8, 14, 30, 0, '+03:30');
$d->gregorian()->format('c');   // "2026-04-08T14:30:00+03:30"
```

## Common pitfall: comparing across timezones

`equals()` and `lessThan()` **do not** translate across zones. If you have two `Instant` values and want to know which represents the earlier physical moment, escape both and compare natively:

```php
$a = Instant::fromGregorian(2026, 4, 8, 14, 30, 0, 'Asia/Tehran');
$b = Instant::fromGregorian(2026, 4, 8, 14, 30, 0, 'UTC');

$a->equals($b);        // true — civil-time equality (surprising!)
$a->toDateTimeImmutable() == $b->toDateTimeImmutable();   // false
$a->toDateTimeImmutable() < $b->toDateTimeImmutable();    // true (Tehran 14:30 is 11:00 UTC)
```

See [faq.md](faq.md#why-isnt-equals-timezone-aware) for the rationale.

## See also

- [concepts.md](concepts.md#instant-is-civil-not-utc) — civil vs. UTC
- [serialization.md](serialization.md) — how `tzLabel` round-trips through JSON
- [exceptions.md](exceptions.md#missingtimezoneexception) — `MissingTimezoneException`
- [cookbook.md](cookbook.md#do-timezone-math-by-escape-hatching-to-datetimeimmutable) — worked example
