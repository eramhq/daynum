# Serialization

Daynum's serialization contract is deliberately simple: an `Instant` serializes to a three-field JSON object, and the round-trip is exact. The format is calendar-neutral — you never need to re-specify which calendar produced the value.

## The JSON round-trip contract

```php
$d = Instant::fromJalali(1405, 1, 19, 14, 30, 0, 'Asia/Tehran');

json_encode($d);
// {"jdn":2461139,"secondsOfDay":52200,"tzLabel":"Asia/Tehran"}
```

The three fields are:

| Field | Type | Meaning |
|---|---|---|
| `jdn` | `int` | Julian Day Number — the interlingua between all calendars |
| `secondsOfDay` | `int` | Seconds since midnight, in `[0, 86400)` |
| `tzLabel` | `string\|null` | IANA name, fixed offset, or `null` |

Nothing else. No calendar identifier (it doesn't belong — JDN is neutral), no locale, no format string. These are all read/display concerns that belong on the view, not on the stored value.

## Reconstructing

```php
$data = json_decode($json, true);
$d = Instant::fromArray($data);
```

`Instant::fromArray` validates that `jdn` is an int, `secondsOfDay` is an int (defaulting to `0`), and `tzLabel` is a string or `null` (defaulting to `null`). Missing `secondsOfDay` and `tzLabel` are allowed:

```php
Instant::fromArray(['jdn' => 2461139]);  // works — time 00:00:00, no tz
```

Invalid input throws `InvalidArgumentException`:

```php
Instant::fromArray(['jdn' => '2461139']);   // throws — jdn must be int
Instant::fromArray([]);                      // throws — missing jdn
```

## Calendar-specific array form

For debugging or when you want human-readable fields, views also expose `toArray()`:

```php
$d->jalali()->toArray();
// ['year'=>1405,'month'=>1,'day'=>19,'hour'=>14,'minute'=>30,'second'=>0,'tzLabel'=>'Asia/Tehran']

$d->gregorian()->toArray();
// ['year'=>2026,'month'=>4,'day'=>8,'hour'=>14,'minute'=>30,'second'=>0,'tzLabel'=>'Asia/Tehran']
```

**Important:** there is no inverse of `view->toArray()`. It's not a round-trip format — it's calendar-specific, so reconstructing from it would require choosing which calendar to reconstruct through.

For round-trippable storage, use `json_encode($instant)` / `Instant::fromArray()`. Use `view->toArray()` for display, logging, and template rendering.

## DB persistence patterns

### Pattern 1: three columns (recommended)

Store the three fields directly. This is the cleanest schema and plays nicely with indexes and range queries.

```sql
CREATE TABLE events (
    id           BIGSERIAL PRIMARY KEY,
    title        TEXT NOT NULL,
    event_jdn    INTEGER NOT NULL,                -- day, orderable
    event_sod    INTEGER NOT NULL DEFAULT 0,      -- time-of-day
    event_tz     TEXT,                            -- nullable
    INDEX (event_jdn)
);
```

```php
// Store
$stmt->execute([
    'event_jdn' => $d->jdn,
    'event_sod' => $d->secondsOfDay,
    'event_tz'  => $d->tzLabel,
]);

// Read
$d = new Instant(
    jdn: $row['event_jdn'],
    secondsOfDay: $row['event_sod'],
    tzLabel: $row['event_tz'],
);
```

Range queries use plain integer comparisons on `event_jdn`, which is fast and index-friendly regardless of which calendar the input came from.

### Pattern 2: one JSON column

If your ORM or framework prefers a single column:

```sql
ALTER TABLE events ADD COLUMN event_instant JSONB NOT NULL;
```

```php
// Store
$stmt->execute(['event_instant' => json_encode($d)]);

// Read
$d = Instant::fromArray(json_decode($row['event_instant'], true));
```

Simpler, but harder to query — you lose the ability to do an index-backed `WHERE event_jdn BETWEEN x AND y` without generated columns.

### Pattern 3: native PHP datetime (interop)

If you need to share a column with legacy code that already uses `DATETIME` / `TIMESTAMPTZ`, escape-hatch to `DateTimeImmutable`:

```php
// Store
$native = $d->toDateTimeImmutable();
$stmt->execute(['event_at' => $native->format('Y-m-d H:i:s'), 'event_tz' => $d->tzLabel]);

// Read — two round trips
$native = new DateTimeImmutable($row['event_at'], new DateTimeZone($row['event_tz'] ?? 'UTC'));
$d = Instant::fromDateTime($native);
```

Note that `DATETIME` columns don't store timezones — you need a second column for that unless your DB has a `TIMESTAMPTZ` type (Postgres does; MySQL does not).

## Storage size

Three integers plus an optional short string. On PostgreSQL with the three-column schema, each `Instant` takes approximately `4 + 4 + 20 = 28` bytes. On a JSONB column, about 60 bytes with field names.

## Calendar-neutrality is the whole point

Because JDN is the interlingua, you never need to re-declare which calendar produced the value. Write Jalali input, read as Gregorian:

```php
// Request comes in as Jalali
$input = Instant::fromJalali(1405, 1, 19, 14, 30, 0, 'Asia/Tehran');
$pdo->exec("INSERT INTO events (event_jdn, event_sod, event_tz) VALUES ({$input->jdn}, {$input->secondsOfDay}, 'Asia/Tehran')");

// Much later, display in English Gregorian
$row = $pdo->query("SELECT * FROM events WHERE id = 1")->fetch();
$d = new Instant($row['event_jdn'], $row['event_sod'], $row['event_tz']);
echo $d->gregorian()->format('Y-m-d H:i e');  // "2026-04-08 14:30 Asia/Tehran"
```

Mixing input and output calendars is free — no conversion code, no migration scripts.

## What `Instant` is not

`Instant` is civil, not UTC. Two `Instant` objects with the same `jdn` + `secondsOfDay` but different `tzLabel` will round-trip to the same string representation and compare `equals()`. If you need a physical-time-ordered column for a job queue or audit log, store the Unix timestamp alongside (`$d->toDateTimeImmutable()->getTimestamp()`) or use a `TIMESTAMPTZ` column instead.

See [concepts.md](concepts.md#instant-is-civil-not-utc).

## See also

- [timezones.md](timezones.md) — what `tzLabel` does and doesn't do
- [cookbook.md](cookbook.md#persist-and-reload-an-instant-via-json) — a full Laravel-style example
- [api-reference.md](api-reference.md#instant) — `fromArray`, `jsonSerialize`, `toArray`
