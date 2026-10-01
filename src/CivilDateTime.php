<?php

declare(strict_types=1);

namespace Eram\Daynum;

use Eram\Daynum\Calendar\Gregorian\GregorianCalendar;
use Eram\Daynum\Calendar\Gregorian\GregorianView;
use Eram\Daynum\Calendar\Hijri\HijriCivilCalendar;
use Eram\Daynum\Calendar\Hijri\HijriCivilView;
use Eram\Daynum\Calendar\Hijri\HijriUmmAlQuraCalendar;
use Eram\Daynum\Calendar\Hijri\HijriUmmAlQuraView;
use Eram\Daynum\Calendar\Jalali\JalaliCalendar;
use Eram\Daynum\Calendar\Jalali\JalaliView;
use Eram\Daynum\Exception\InvalidDateException;
use Eram\Daynum\Exception\InvalidTimezoneException;
use Eram\Daynum\Exception\MissingTimezoneException;
use Eram\Daynum\Exception\UmmAlQuraOutOfRangeException;
use Eram\Daynum\Internal\IntMath;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use JsonSerializable;

/**
 * The immutable, calendar-agnostic core value type: a wall-clock date and
 * time-of-day, plus an optional timezone label.
 *
 * A CivilDateTime is the triple (JDN, time-of-day in seconds, opaque timezone label).
 * It is not itself "in" any calendar — viewing it in one of the shipped calendars
 * is done via `$i->gregorian()` or `$i->jalali()`.
 *
 * The Julian Day Number (JDN) is the interlingua between all calendars. All
 * calendar arithmetic composes through it.
 *
 * `secondsOfDay` and `tzLabel` are pass-through metadata. Calendar conversions
 * never touch them; formatting uses them for time/zone tokens only.
 *
 * Comparison methods (equals, lessThan, compare, ...) and the time
 * arithmetic (addHours, diffInSeconds, ...) work on wall-clock readings and
 * ignore tzLabel; they are not DST-aware. For timeline order or exact
 * elapsed time across timezones, use toTimestamp() or toDateTimeImmutable().
 */
final class CivilDateTime implements JsonSerializable
{
    public function __construct(
        public readonly int $jdn,
        public readonly int $secondsOfDay = 0,
        public readonly ?string $tzLabel = null,
    ) {
        if ($secondsOfDay < 0 || $secondsOfDay >= 86400) {
            throw new InvalidDateException(
                "secondsOfDay must be in [0, 86400); got {$secondsOfDay}.",
            );
        }
    }

    // ─── Construction ────────────────────────────────────────────────

    public static function fromGregorian(
        int $year,
        int $month,
        int $day,
        int $hour = 0,
        int $minute = 0,
        int $second = 0,
        ?string $tzLabel = null,
    ): self {
        $jdn = GregorianCalendar::instance()->toJdn($year, $month, $day);
        return new self($jdn, self::encodeTime($hour, $minute, $second), $tzLabel);
    }

    public static function fromJalali(
        int $year,
        int $month,
        int $day,
        int $hour = 0,
        int $minute = 0,
        int $second = 0,
        ?string $tzLabel = null,
    ): self {
        $jdn = JalaliCalendar::instance()->toJdn($year, $month, $day);
        return new self($jdn, self::encodeTime($hour, $minute, $second), $tzLabel);
    }

    /**
     * Construct from a Saudi Umm al-Qura (KACST) Hijri date.
     *
     * This is the default Hijri entry point because the Saudi/Gulf audience
     * and most Persian websites that display Hijri dates expect Umm al-Qura
     * specifically. For dates outside the bundled table range, Daynum
     * throws rather than silently falling back to the civil calendar — use
     * {@see fromHijriCivil} explicitly when you need far-historical or
     * far-future dates.
     *
     * @throws \Eram\Daynum\Exception\UmmAlQuraOutOfRangeException
     * @throws \Eram\Daynum\Exception\InvalidDateException
     */
    public static function fromHijri(
        int $year,
        int $month,
        int $day,
        int $hour = 0,
        int $minute = 0,
        int $second = 0,
        ?string $tzLabel = null,
    ): self {
        $jdn = HijriUmmAlQuraCalendar::instance()->toJdn($year, $month, $day);
        return new self($jdn, self::encodeTime($hour, $minute, $second), $tzLabel);
    }

    /**
     * Construct from a tabular Hijri (arithmetic Islamic "civil") date.
     *
     * Works for any AH year in `[HijriCivilCalendar::MIN_YEAR,
     * HijriCivilCalendar::MAX_YEAR]` — no Umm al-Qura table lookup is
     * involved. For the Saudi Umm al-Qura calendar, use {@see fromHijri}.
     */
    public static function fromHijriCivil(
        int $year,
        int $month,
        int $day,
        int $hour = 0,
        int $minute = 0,
        int $second = 0,
        ?string $tzLabel = null,
    ): self {
        $jdn = HijriCivilCalendar::instance()->toJdn($year, $month, $day);
        return new self($jdn, self::encodeTime($hour, $minute, $second), $tzLabel);
    }

    /**
     * Adapter from native PHP DateTime/DateTimeImmutable.
     *
     * Reads the proleptic Gregorian calendar date, the time-of-day, and the
     * timezone name from the given DateTimeInterface.
     */
    public static function fromDateTime(DateTimeInterface $dt): self
    {
        $year = (int) $dt->format('Y');
        $month = (int) $dt->format('n');
        $day = (int) $dt->format('j');
        $hour = (int) $dt->format('G');
        $minute = (int) $dt->format('i');
        $second = (int) $dt->format('s');
        $tzName = $dt->getTimezone()->getName();

        return self::fromGregorian($year, $month, $day, $hour, $minute, $second, $tzName);
    }

    /**
     * The wall-clock reading of a Unix timestamp in the given timezone.
     *
     * `CivilDateTime::fromTimestamp(0)` is 1970-01-01 00:00:00 with
     * tzLabel `'UTC'`; `fromTimestamp(0, 'Asia/Tehran')` is 03:30:00 with
     * tzLabel `'Asia/Tehran'`.
     *
     * @param string $tzLabel IANA identifier or UTC offset (e.g. '+03:30').
     *
     * @throws InvalidTimezoneException if the timezone is unknown
     */
    public static function fromTimestamp(int $timestamp, string $tzLabel = 'UTC'): self
    {
        $dt = (new DateTimeImmutable('@' . $timestamp))->setTimezone(self::zone($tzLabel));

        return self::fromDateTime($dt);
    }

    /**
     * Current date and time.
     *
     * When no timezone is provided, the PHP default timezone is used for
     * determining the current date/time AND is stored on the CivilDateTime (matching
     * how {@see fromDateTime()} resolves the timezone from a DateTimeInterface).
     *
     * @param ?string $tzLabel Timezone identifier (e.g. 'Asia/Tehran', 'UTC').
     *                        When null, resolves from `date_default_timezone_get()`.
     */
    public static function now(?string $tzLabel = null): self
    {
        $now = new DateTimeImmutable('now', self::resolveZone($tzLabel));

        return self::fromDateTime($now);
    }

    /**
     * Today in the proleptic Gregorian calendar, time = 00:00:00.
     *
     * When no timezone is provided, the PHP default timezone is used for
     * determining today's date AND is stored on the CivilDateTime (matching
     * how {@see now()} resolves the timezone).
     *
     * @param ?string $tzLabel Timezone identifier (e.g. 'Asia/Tehran', 'UTC').
     *                        When null, resolves from `date_default_timezone_get()`.
     */
    public static function today(?string $tzLabel = null): self
    {
        $now = new DateTimeImmutable('today', self::resolveZone($tzLabel));

        return self::fromDateTime($now);
    }

    /**
     * Tomorrow at 00:00:00.
     *
     * @param ?string $tzLabel Timezone identifier (e.g. 'Asia/Tehran', 'UTC').
     *                        When null, resolves from `date_default_timezone_get()`.
     */
    public static function tomorrow(?string $tzLabel = null): self
    {
        $today = self::today($tzLabel);
        return $today->withJdn($today->jdn + 1);
    }

    /**
     * Yesterday at 00:00:00.
     *
     * @param ?string $tzLabel Timezone identifier (e.g. 'Asia/Tehran', 'UTC').
     *                        When null, resolves from `date_default_timezone_get()`.
     */
    public static function yesterday(?string $tzLabel = null): self
    {
        $today = self::today($tzLabel);
        return $today->withJdn($today->jdn - 1);
    }

    // ─── Safe construction ────────────────────────────────────────

    /**
     * Try to construct from Gregorian components; return null on invalid input.
     */
    public static function tryFromGregorian(
        int $year,
        int $month,
        int $day,
        int $hour = 0,
        int $minute = 0,
        int $second = 0,
        ?string $tzLabel = null,
    ): ?self {
        try {
            return self::fromGregorian($year, $month, $day, $hour, $minute, $second, $tzLabel);
        } catch (InvalidDateException) {
            return null;
        }
    }

    /**
     * Try to construct from Jalali components; return null on invalid input.
     */
    public static function tryFromJalali(
        int $year,
        int $month,
        int $day,
        int $hour = 0,
        int $minute = 0,
        int $second = 0,
        ?string $tzLabel = null,
    ): ?self {
        try {
            return self::fromJalali($year, $month, $day, $hour, $minute, $second, $tzLabel);
        } catch (InvalidDateException) {
            return null;
        }
    }

    /**
     * Try to construct from Hijri Umm al-Qura components; return null on
     * invalid input OR if the year is outside the bundled table range.
     */
    public static function tryFromHijri(
        int $year,
        int $month,
        int $day,
        int $hour = 0,
        int $minute = 0,
        int $second = 0,
        ?string $tzLabel = null,
    ): ?self {
        try {
            return self::fromHijri($year, $month, $day, $hour, $minute, $second, $tzLabel);
        } catch (InvalidDateException | UmmAlQuraOutOfRangeException) {
            return null;
        }
    }

    /**
     * Try to construct from tabular Hijri civil components; return null on
     * invalid input.
     */
    public static function tryFromHijriCivil(
        int $year,
        int $month,
        int $day,
        int $hour = 0,
        int $minute = 0,
        int $second = 0,
        ?string $tzLabel = null,
    ): ?self {
        try {
            return self::fromHijriCivil($year, $month, $day, $hour, $minute, $second, $tzLabel);
        } catch (InvalidDateException) {
            return null;
        }
    }

    /**
     * Check whether the given Gregorian components form a valid date.
     */
    public static function isValidGregorian(int $year, int $month, int $day): bool
    {
        return self::tryFromGregorian($year, $month, $day) !== null;
    }

    /**
     * Check whether the given Jalali components form a valid date.
     */
    public static function isValidJalali(int $year, int $month, int $day): bool
    {
        return self::tryFromJalali($year, $month, $day) !== null;
    }

    /**
     * Check whether the given Hijri Umm al-Qura components form a valid date
     * within the bundled table range.
     */
    public static function isValidHijri(int $year, int $month, int $day): bool
    {
        return self::tryFromHijri($year, $month, $day) !== null;
    }

    /**
     * Check whether the given tabular Hijri civil components form a valid date.
     */
    public static function isValidHijriCivil(int $year, int $month, int $day): bool
    {
        return self::tryFromHijriCivil($year, $month, $day) !== null;
    }

    // ─── Serialization ─────────────────────────────────────────────

    /**
     * Calendar-neutral JSON representation: `{"jdn":…,"secondsOfDay":…,"tzLabel":…}`.
     *
     * @return array{jdn: int, secondsOfDay: int, tzLabel: ?string}
     */
    public function jsonSerialize(): array
    {
        return [
            'jdn' => $this->jdn,
            'secondsOfDay' => $this->secondsOfDay,
            'tzLabel' => $this->tzLabel,
        ];
    }

    /**
     * Reconstruct a CivilDateTime from a serialized array (inverse of {@see jsonSerialize}).
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        if (!isset($data['jdn']) || !is_int($data['jdn'])) {
            throw new Exception\InvalidArgumentException('CivilDateTime::fromArray() requires an integer "jdn" key.');
        }

        $secondsOfDay = $data['secondsOfDay'] ?? 0;
        if (!is_int($secondsOfDay)) {
            throw new Exception\InvalidArgumentException(
                'CivilDateTime::fromArray() "secondsOfDay" must be an int; got ' . get_debug_type($secondsOfDay) . '.',
            );
        }

        $tzLabel = $data['tzLabel'] ?? null;
        if ($tzLabel !== null && !is_string($tzLabel)) {
            throw new Exception\InvalidArgumentException(
                'CivilDateTime::fromArray() "tzLabel" must be a string or null; got ' . get_debug_type($tzLabel) . '.',
            );
        }

        return new self($data['jdn'], $secondsOfDay, $tzLabel);
    }

    // ─── Calendar views ──────────────────────────────────────────────

    public function gregorian(): GregorianView
    {
        return GregorianView::of($this);
    }

    public function jalali(): JalaliView
    {
        return JalaliView::of($this);
    }

    /**
     * View as a Saudi Umm al-Qura (KACST) Hijri date.
     *
     * Component accessors and formatting on this view can throw
     * {@see \Eram\Daynum\Exception\UmmAlQuraOutOfRangeException} if the
     * underlying JDN falls outside the bundled table — use
     * {@see hijriCivil()} for dates outside that window.
     */
    public function hijri(): HijriUmmAlQuraView
    {
        return HijriUmmAlQuraView::of($this);
    }

    /**
     * View as a tabular Hijri (arithmetic Islamic "civil") date.
     *
     * Unlike {@see hijri()}, this view has no bounded range — it works for
     * any JDN the civil calendar can represent (AH 1..9666). Prefer
     * {@see hijri()} for Saudi/Gulf audiences who expect Umm al-Qura;
     * use this view for historical and far-future dates.
     */
    public function hijriCivil(): HijriCivilView
    {
        return HijriCivilView::of($this);
    }

    // ─── Comparison ──────────────────────────────────────────────────

    public function equals(self $other): bool
    {
        return $this->jdn === $other->jdn
            && $this->secondsOfDay === $other->secondsOfDay;
    }

    public function lessThan(self $other): bool
    {
        return self::compare($this, $other) < 0;
    }

    public function greaterThan(self $other): bool
    {
        return self::compare($this, $other) > 0;
    }

    public function lessThanOrEqual(self $other): bool
    {
        return self::compare($this, $other) <= 0;
    }

    public function greaterThanOrEqual(self $other): bool
    {
        return self::compare($this, $other) >= 0;
    }

    /**
     * Three-way comparison of wall-clock readings: negative when `$a` is
     * earlier, zero when equal, positive when later. Usable directly as a
     * `usort()` callback: `usort($dates, CivilDateTime::compare(...))`.
     */
    public static function compare(self $a, self $b): int
    {
        return $a->jdn <=> $b->jdn ?: $a->secondsOfDay <=> $b->secondsOfDay;
    }

    /**
     * The earliest of the given values (the first one on a tie).
     */
    public static function min(self $first, self ...$rest): self
    {
        foreach ($rest as $candidate) {
            if (self::compare($candidate, $first) < 0) {
                $first = $candidate;
            }
        }
        return $first;
    }

    /**
     * The latest of the given values (the first one on a tie).
     */
    public static function max(self $first, self ...$rest): self
    {
        foreach ($rest as $candidate) {
            if (self::compare($candidate, $first) > 0) {
                $first = $candidate;
            }
        }
        return $first;
    }

    /**
     * Whether this value lies between `$a` and `$b`, in either order.
     *
     * @param bool $inclusive whether a value equal to either bound counts
     */
    public function between(self $a, self $b, bool $inclusive = true): bool
    {
        $low = self::min($a, $b);
        $high = self::max($a, $b);
        $fromLow = self::compare($this, $low);
        $toHigh = self::compare($this, $high);

        return $inclusive
            ? $fromLow >= 0 && $toHigh <= 0
            : $fromLow > 0 && $toHigh < 0;
    }

    /**
     * Whether both values fall on the same calendar day (time-of-day ignored).
     */
    public function isSameDay(self $other): bool
    {
        return $this->jdn === $other->jdn;
    }

    /**
     * Signed difference in whole days: other subtracted from this.
     *
     * `CivilDateTime::fromGregorian(2026, 4, 10)->diffInDays(CivilDateTime::fromGregorian(2026, 4, 8))`
     * returns 2.
     */
    public function diffInDays(self $other): int
    {
        return $this->jdn - $other->jdn;
    }

    /**
     * Signed wall-clock difference in seconds: other subtracted from this.
     *
     * Not DST-aware: 01:00 → 03:00 is 7200 seconds even across a
     * spring-forward gap. For exact elapsed time, diff `toTimestamp()`.
     */
    public function diffInSeconds(self $other): int
    {
        return ($this->jdn - $other->jdn) * 86400 + ($this->secondsOfDay - $other->secondsOfDay);
    }

    /**
     * Signed wall-clock difference in whole minutes, truncated toward zero.
     */
    public function diffInMinutes(self $other): int
    {
        return intdiv($this->diffInSeconds($other), 60);
    }

    /**
     * Signed wall-clock difference in whole hours, truncated toward zero.
     */
    public function diffInHours(self $other): int
    {
        return intdiv($this->diffInSeconds($other), 3600);
    }

    // ─── Time arithmetic (wall-clock) ────────────────────────────────

    /**
     * Move the wall-clock reading by a number of seconds, rolling over
     * midnight into the neighbouring days. Not DST-aware: adding one hour
     * to 01:30 always gives 02:30, even on a day the clocks skip that hour.
     */
    public function addSeconds(int $seconds): self
    {
        return $this->shift($seconds, 1);
    }

    public function subSeconds(int $seconds): self
    {
        return $this->shift(-$seconds, 1);
    }

    public function addMinutes(int $minutes): self
    {
        return $this->shift($minutes, 60);
    }

    public function subMinutes(int $minutes): self
    {
        return $this->shift(-$minutes, 60);
    }

    public function addHours(int $hours): self
    {
        return $this->shift($hours, 3600);
    }

    public function subHours(int $hours): self
    {
        return $this->shift(-$hours, 3600);
    }

    /**
     * Days are calendar-neutral, so this matches `$d->gregorian()->addDays()`
     * (and every other view's `addDays`).
     */
    public function addDays(int $days): self
    {
        return $this->withJdn($this->jdn + $days);
    }

    public function subDays(int $days): self
    {
        return $this->addDays(-$days);
    }

    public function addWeeks(int $weeks): self
    {
        return $this->addDays($weeks * 7);
    }

    public function subWeeks(int $weeks): self
    {
        return $this->addDays(-$weeks * 7);
    }

    /** Same day at 00:00:00. */
    public function startOfDay(): self
    {
        return new self($this->jdn, 0, $this->tzLabel);
    }

    /** Same day at 23:59:59. */
    public function endOfDay(): self
    {
        return new self($this->jdn, 86399, $this->tzLabel);
    }

    // ─── Mutation-as-new (used by views) ─────────────────────────────

    public function withJdn(int $jdn): self
    {
        return new self($jdn, $this->secondsOfDay, $this->tzLabel);
    }

    public function withTime(int $hour, int $minute, int $second): self
    {
        return new self($this->jdn, self::encodeTime($hour, $minute, $second), $this->tzLabel);
    }

    public function withTzLabel(?string $tzLabel): self
    {
        return new self($this->jdn, $this->secondsOfDay, $tzLabel);
    }

    // ─── Escape hatch ────────────────────────────────────────────────

    /**
     * Hand off to native DateTimeImmutable for actual timezone math.
     *
     * The Gregorian Y/M/D is derived from the JDN via the proleptic Gregorian
     * calendar, then the time-of-day and optional timezone label are applied.
     * A null label uses PHP's default timezone.
     *
     * DST edge cases follow PHP's parser: a reading inside a spring-forward
     * gap moves forward by the gap, and an ambiguous fall-back reading
     * resolves to the first (earlier) moment.
     */
    public function toDateTimeImmutable(): DateTimeImmutable
    {
        [$year, $month, $day] = GregorianCalendar::instance()->fromJdn($this->jdn);

        // Parse a literal string rather than calling setDate()/setTime() on
        // 'now': setTime() resolves an ambiguous reading using the base
        // object's current DST state, so the result would depend on the
        // date the code runs.
        $text = sprintf(
            '%s%04d-%02d-%02d %02d:%02d:%02d',
            $year < 0 ? '-' : '+',   // explicit sign: PHP rejects unsigned 5-digit years
            abs($year),
            $month,
            $day,
            intdiv($this->secondsOfDay, 3600),
            intdiv($this->secondsOfDay % 3600, 60),
            $this->secondsOfDay % 60,
        );

        return new DateTimeImmutable($text, self::resolveZone($this->tzLabel));
    }

    /**
     * Unix timestamp of this wall-clock reading in its timezone.
     *
     * Delegates to {@see toDateTimeImmutable()}, so DST edge cases resolve
     * the same way: gap readings move forward, ambiguous readings resolve to
     * the earlier moment.
     *
     * @throws MissingTimezoneException if tzLabel is null — there is no
     *         single moment without a timezone, and silently using PHP's
     *         default would make the result depend on server config.
     * @throws InvalidTimezoneException if tzLabel is unknown
     */
    public function toTimestamp(): int
    {
        if ($this->tzLabel === null) {
            throw MissingTimezoneException::forOperation('toTimestamp()');
        }

        return $this->toDateTimeImmutable()->getTimestamp();
    }

    // ─── Internals ───────────────────────────────────────────────────

    /**
     * Shift by `$amount` units of `$unitSeconds` (which must divide 86400).
     * Whole days are split off before multiplying, so large amounts cannot
     * overflow the seconds arithmetic.
     */
    private function shift(int $amount, int $unitSeconds): self
    {
        $unitsPerDay = intdiv(86400, $unitSeconds);
        $seconds = $this->secondsOfDay + ($amount % $unitsPerDay) * $unitSeconds;

        return new self(
            $this->jdn + intdiv($amount, $unitsPerDay) + IntMath::floorDiv($seconds, 86400),
            IntMath::floorMod($seconds, 86400),
            $this->tzLabel,
        );
    }

    private static function resolveZone(?string $tzLabel): ?DateTimeZone
    {
        return $tzLabel === null ? null : self::zone($tzLabel);
    }

    private static function zone(string $tzLabel): DateTimeZone
    {
        try {
            return new DateTimeZone($tzLabel);
        } catch (\Exception) {
            throw InvalidTimezoneException::forLabel($tzLabel);
        }
    }

    private static function encodeTime(int $hour, int $minute, int $second): int
    {
        if ($hour < 0 || $hour > 23 || $minute < 0 || $minute > 59 || $second < 0 || $second > 59) {
            throw InvalidDateException::forTime($hour, $minute, $second);
        }
        return $hour * 3600 + $minute * 60 + $second;
    }
}
