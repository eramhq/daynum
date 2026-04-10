<?php

declare(strict_types=1);

namespace Daynum;

use Daynum\Calendar\Gregorian\GregorianCalendar;
use Daynum\Calendar\Gregorian\GregorianView;
use Daynum\Calendar\Hijri\HijriCivilCalendar;
use Daynum\Calendar\Hijri\HijriCivilView;
use Daynum\Calendar\Hijri\HijriUmmAlQuraCalendar;
use Daynum\Calendar\Hijri\HijriUmmAlQuraView;
use Daynum\Calendar\Jalali\JalaliCalendar;
use Daynum\Calendar\Jalali\JalaliView;
use Daynum\Exception\InvalidDateException;
use Daynum\Exception\InvalidTimezoneException;
use Daynum\Exception\UmmAlQuraOutOfRangeException;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use JsonSerializable;

/**
 * The immutable, calendar-agnostic core value type.
 *
 * An Instant is the triple (JDN, time-of-day in seconds, opaque timezone label).
 * It is not itself "in" any calendar — viewing it in one of the shipped calendars
 * is done via `$i->gregorian()` or `$i->jalali()`.
 *
 * The Julian Day Number (JDN) is the interlingua between all calendars. All
 * calendar arithmetic composes through it.
 *
 * `secondsOfDay` and `tzLabel` are pass-through metadata. Calendar conversions
 * never touch them; formatting uses them for time/zone tokens only.
 *
 * ## Naming note
 *
 * Daynum's Instant is NOT a UTC timeline instant (unlike java.time.Instant).
 * It is a calendar-neutral civil datetime: (JDN, time-of-day, timezone label).
 * Two Instants with the same JDN and time but different tzLabels represent
 * different physical moments. Comparison methods (equals, lessThan, etc.)
 * compare wall-clock readings, not physical instants. For timeline-order
 * comparison across timezones, convert to DateTimeImmutable first.
 */
final class Instant implements JsonSerializable
{
    public function __construct(
        public readonly int $jdn,
        public readonly int $secondsOfDay = 0,
        public readonly ?string $tzLabel = null,
    ) {
        if ($secondsOfDay < 0 || $secondsOfDay >= 86400) {
            throw new InvalidDateException(
                "secondsOfDay must be in [0, 86400); got {$secondsOfDay}."
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
     * @throws \Daynum\Exception\UmmAlQuraOutOfRangeException
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
        $tz = $dt->getTimezone();
        $tzName = $tz !== false ? $tz->getName() : null;

        return self::fromGregorian($year, $month, $day, $hour, $minute, $second, $tzName);
    }

    /**
     * Current date and time.
     *
     * When no timezone is provided, the PHP default timezone is used for
     * determining the current date/time AND is stored on the Instant (matching
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
     * determining today's date AND is stored on the Instant (matching
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
     * Reconstruct an Instant from a serialized array (inverse of {@see jsonSerialize}).
     *
     * @param array{jdn: int, secondsOfDay?: int, tzLabel?: ?string} $data
     */
    public static function fromArray(array $data): self
    {
        if (!isset($data['jdn']) || !is_int($data['jdn'])) {
            throw new \InvalidArgumentException('Instant::fromArray() requires an integer "jdn" key.');
        }

        return new self(
            $data['jdn'],
            $data['secondsOfDay'] ?? 0,
            $data['tzLabel'] ?? null,
        );
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
     * {@see \Daynum\Exception\UmmAlQuraOutOfRangeException} if the
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
        return $this->compareTo($other) < 0;
    }

    public function greaterThan(self $other): bool
    {
        return $this->compareTo($other) > 0;
    }

    public function lessThanOrEqual(self $other): bool
    {
        return $this->compareTo($other) <= 0;
    }

    public function greaterThanOrEqual(self $other): bool
    {
        return $this->compareTo($other) >= 0;
    }

    /**
     * Signed difference in whole days: other subtracted from this.
     *
     * `Instant::fromGregorian(2026, 4, 10)->diffInDays(Instant::fromGregorian(2026, 4, 8))`
     * returns 2.
     */
    public function diffInDays(self $other): int
    {
        return $this->jdn - $other->jdn;
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
     */
    public function toDateTimeImmutable(): DateTimeImmutable
    {
        [$year, $month, $day] = GregorianCalendar::instance()->fromJdn($this->jdn);
        $hour = intdiv($this->secondsOfDay, 3600);
        $minute = intdiv($this->secondsOfDay % 3600, 60);
        $second = $this->secondsOfDay % 60;

        return (new DateTimeImmutable('now', self::resolveZone($this->tzLabel)))
            ->setDate($year, $month, $day)
            ->setTime($hour, $minute, $second);
    }

    // ─── Internals ───────────────────────────────────────────────────

    private function compareTo(self $other): int
    {
        if ($this->jdn !== $other->jdn) {
            return $this->jdn <=> $other->jdn;
        }
        return $this->secondsOfDay <=> $other->secondsOfDay;
    }

    private static function resolveZone(?string $tzLabel): ?DateTimeZone
    {
        if ($tzLabel === null) {
            return null;
        }
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
