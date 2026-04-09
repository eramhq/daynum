<?php

declare(strict_types=1);

namespace Daynum\Calendar;

use Daynum\Calendar;
use Daynum\CalendarView;
use Daynum\Exception\DaynumException;
use Daynum\Exception\WeekAtBoundaryException;
use Daynum\Formatter\DateTokenFormatter;
use Daynum\Formatter\DigitTransliterator;
use Daynum\Formatter\FormatContext;
use Daynum\Instant;
use Daynum\Locale\LocaleData;
use Daynum\Locale\LocaleRegistry;

/**
 * Shared implementation of {@see CalendarView} for calendars whose arithmetic
 * can be expressed entirely through the {@see Calendar} interface.
 *
 * Subclasses only need to supply their backing calendar, their default format
 * pattern, and their default locale. Everything else — component accessors,
 * formatting, arithmetic, diffs — is implemented here in terms of the
 * {@see Calendar} methods.
 *
 * Subclasses are final and immutable; `withLocale` / `withDigits` return a
 * new instance of the same concrete type.
 */
abstract class AbstractCalendarView implements CalendarView
{
    /**
     * @var array{year:int, month:int, day:int, daysInMonth:int, isLeapYear:bool}|null
     */
    private ?array $cache = null;

    final protected function __construct(
        protected readonly Instant $instant,
        protected readonly LocaleData $locale,
        protected readonly string $digitScript,
    ) {
    }

    abstract public function calendar(): Calendar;

    /** Default format pattern used by `__toString()`. */
    abstract protected function defaultFormat(): string;

    // ─── Instance factory (used by Instant) ───────────────────────────

    /**
     * @return static
     */
    public static function of(Instant $instant, ?string $locale = null, string $digitScript = DigitTransliterator::LATN): static
    {
        if (!DigitTransliterator::isSupported($digitScript)) {
            throw new \InvalidArgumentException("Unknown digit script '{$digitScript}'.");
        }
        return new static($instant, LocaleRegistry::get($locale ?? 'en'), $digitScript);
    }

    // ─── CalendarView interface ───────────────────────────────────────

    public function instant(): Instant
    {
        return $this->instant;
    }

    public function year(): int
    {
        return $this->components()['year'];
    }

    public function month(): int
    {
        return $this->components()['month'];
    }

    public function day(): int
    {
        return $this->components()['day'];
    }

    public function hour(): int
    {
        return intdiv($this->instant->secondsOfDay, 3600);
    }

    public function minute(): int
    {
        return intdiv($this->instant->secondsOfDay % 3600, 60);
    }

    public function second(): int
    {
        return $this->instant->secondsOfDay % 60;
    }

    public function dayOfWeek(): int
    {
        // JDN 0 was a Monday. PHP's w is Sun=0..Sat=6.
        // JDN 0 (Mon) → w=1. Formula: ((jdn + 1) mod 7), normalized.
        $w = ($this->instant->jdn + 1) % 7;
        if ($w < 0) {
            $w += 7;
        }
        return $w;
    }

    public function dayOfWeekIso(): int
    {
        // Monday = 1, Sunday = 7.
        $iso = $this->instant->jdn % 7;
        if ($iso < 0) {
            $iso += 7;
        }
        return $iso + 1;
    }

    public function dayOfYear(): int
    {
        $c = $this->components();
        return $this->calendar()->dayOfYear($c['year'], $c['month'], $c['day']);
    }

    public function weekOfYear(): int
    {
        // ISO 8601 week number. A week belongs to the year of its Thursday.
        // Algorithm: offset the JDN to the Thursday of its week, then count
        // weeks since the Thursday of week 1 of that year.
        $jdn = $this->instant->jdn;
        $isoDow = $this->dayOfWeekIso();           // Mon=1..Sun=7
        $thursdayJdn = $jdn - $isoDow + 4;          // JDN of this week's Thursday
        $calendar = $this->calendar();

        try {
            [$thursdayYear, , ] = $calendar->fromJdn($thursdayJdn);
            // JDN of Jan 4 of $thursdayYear — always in ISO week 1.
            $jan4 = $calendar->toJdn($thursdayYear, 1, 4);
        } catch (DaynumException $e) {
            // A sentinel `1` would collide with the real week 1 at the MIN
            // edge and be indistinguishable from the current year's week 1
            // at the MAX edge — throwing is the only non-misleading outcome.
            throw WeekAtBoundaryException::forJdn($thursdayJdn, $e);
        }

        $jan4Iso = (($jan4 % 7) + 7) % 7 + 1;
        $firstThursday = $jan4 - $jan4Iso + 4;

        return intdiv($thursdayJdn - $firstThursday, 7) + 1;
    }

    public function weekBasedYear(): int
    {
        // ISO 8601 week-based year — the year owning the ISO week of this
        // date's Thursday. Differs from `year()` by ±1 around Jan 1 / Dec 31.
        $jdn = $this->instant->jdn;
        $isoDow = $this->dayOfWeekIso();
        $thursdayJdn = $jdn - $isoDow + 4;
        $calendar = $this->calendar();

        try {
            [$thursdayYear, , ] = $calendar->fromJdn($thursdayJdn);
        } catch (DaynumException $e) {
            throw WeekAtBoundaryException::forJdn($thursdayJdn, $e);
        }

        // Fast path: when the Thursday falls in the view's own year, the
        // year is known-valid by construction and no probe is needed. This
        // covers roughly 361/365 days — only early-Jan / late-Dec dates
        // need to validate a notionally-adjacent year.
        if ($thursdayYear === $this->components()['year']) {
            return $thursdayYear;
        }

        // Closed-form `fromJdn` implementations (HijriCivil, Jalali,
        // Gregorian) can return a notional out-of-range year that only
        // `toJdn` would reject. Validate by attempting Jan 4 — any year
        // ISO week 1 references must itself be a valid year of this calendar.
        try {
            $calendar->toJdn($thursdayYear, 1, 4);
        } catch (DaynumException $e) {
            throw WeekAtBoundaryException::forYear($thursdayYear, $e);
        }

        return $thursdayYear;
    }

    public function isLeapYear(): bool
    {
        return $this->components()['isLeapYear'];
    }

    public function daysInMonth(): int
    {
        return $this->components()['daysInMonth'];
    }

    public function daysInYear(): int
    {
        $calendar = $this->calendar();
        $year = $this->year();
        $total = 0;
        $monthsInYear = $calendar->monthsInYear($year);
        for ($m = 1; $m <= $monthsInYear; $m++) {
            $total += $calendar->daysInMonth($year, $m);
        }
        return $total;
    }

    // ─── Formatting ───────────────────────────────────────────────────

    public function format(string $pattern): string
    {
        $c = $this->components();
        $calendar = $this->calendar();
        // `dayOfYear`, `weekOfYear`, and `weekBasedYear` each trigger real
        // work — UAQ's dayOfYear is a bit-walk, and the week methods can
        // throw at calendar boundaries. The `str_contains` pre-check is a
        // zero-allocation `memchr`; if the token doesn't appear at all, the
        // escape-aware scan is never reached. `\W` / `\o` / `\z` patterns
        // hit `str_contains` as a false positive, then `patternContainsUnescaped`
        // rejects them — important because it also means an escaped token
        // at a calendar boundary never trips the throw.
        $dayOfYear     = str_contains($pattern, 'z') && self::patternContainsUnescaped($pattern, 'z') ? $this->dayOfYear()     : 0;
        $weekOfYear    = str_contains($pattern, 'W') && self::patternContainsUnescaped($pattern, 'W') ? $this->weekOfYear()    : 0;
        $weekBasedYear = str_contains($pattern, 'o') && self::patternContainsUnescaped($pattern, 'o') ? $this->weekBasedYear() : 0;
        $ctx = new FormatContext(
            locale: $this->locale,
            calendarName: $calendar->localeFamily(),
            year: $c['year'],
            month: $c['month'],
            day: $c['day'],
            hour: $this->hour(),
            minute: $this->minute(),
            second: $this->second(),
            dayOfWeek: $this->dayOfWeek(),
            dayOfWeekIso: $this->dayOfWeekIso(),
            daysInMonth: $c['daysInMonth'],
            dayOfYear: $dayOfYear,
            weekOfYear: $weekOfYear,
            weekBasedYear: $weekBasedYear,
            isLeapYear: $c['isLeapYear'],
            tzLabel: $this->instant->tzLabel,
            digitScript: $this->digitScript,
        );
        return DateTokenFormatter::format($pattern, $ctx);
    }

    /**
     * Returns true if `$char` appears unescaped in `$pattern`. A backslash
     * escapes the next character, so `\W` does not count as a `W`. Runs in
     * O(n) with no regex — roughly 20 ns per short pattern.
     */
    private static function patternContainsUnescaped(string $pattern, string $char): bool
    {
        $len = strlen($pattern);
        for ($i = 0; $i < $len; $i++) {
            if ($pattern[$i] === '\\') {
                $i++;
                continue;
            }
            if ($pattern[$i] === $char) {
                return true;
            }
        }
        return false;
    }

    public function __toString(): string
    {
        return $this->format($this->defaultFormat());
    }

    public function withLocale(string $locale): static
    {
        return new static($this->instant, LocaleRegistry::get($locale), $this->digitScript);
    }

    public function withDigits(string $script): static
    {
        if (!DigitTransliterator::isSupported($script)) {
            throw new \InvalidArgumentException("Unknown digit script '{$script}'.");
        }
        return new static($this->instant, $this->locale, $script);
    }

    // ─── Arithmetic ───────────────────────────────────────────────────

    public function addDays(int $days): Instant
    {
        return $this->instant->withJdn($this->instant->jdn + $days);
    }

    public function subDays(int $days): Instant
    {
        return $this->addDays(-$days);
    }

    public function addMonths(int $months): Instant
    {
        $c = $this->components();
        $calendar = $this->calendar();
        $year = $c['year'];
        $month = $c['month'];

        // Chunk by year so calendars whose year length varies across years
        // (Hebrew's 12-or-13-month year, future) stay correct. For v1 every
        // year has 12 months so this loop runs |months|/12 times.
        if ($months >= 0) {
            while (true) {
                $monthsInYear = $calendar->monthsInYear($year);
                if ($month + $months <= $monthsInYear) {
                    $month += $months;
                    break;
                }
                $months -= ($monthsInYear - $month + 1);
                $year++;
                $month = 1;
            }
        } else {
            $months = -$months;
            while (true) {
                if ($month - $months >= 1) {
                    $month -= $months;
                    break;
                }
                $months -= $month;
                $year--;
                $month = $calendar->monthsInYear($year);
            }
        }

        $dim = $calendar->daysInMonth($year, $month);
        $newDay = min($c['day'], $dim);
        return $this->instant->withJdn($calendar->toJdn($year, $month, $newDay));
    }

    public function subMonths(int $months): Instant
    {
        return $this->addMonths(-$months);
    }

    public function addYears(int $years): Instant
    {
        $c = $this->components();
        $newYear = $c['year'] + $years;
        $calendar = $this->calendar();
        $dim = $calendar->daysInMonth($newYear, $c['month']);
        $newDay = min($c['day'], $dim);
        return $this->instant->withJdn($calendar->toJdn($newYear, $c['month'], $newDay));
    }

    public function subYears(int $years): Instant
    {
        return $this->addYears(-$years);
    }

    public function startOfMonth(): Instant
    {
        $c = $this->components();
        return $this->instant->withJdn($this->calendar()->toJdn($c['year'], $c['month'], 1));
    }

    public function endOfMonth(): Instant
    {
        $c = $this->components();
        return $this->instant->withJdn(
            $this->calendar()->toJdn($c['year'], $c['month'], $c['daysInMonth'])
        );
    }

    public function startOfYear(): Instant
    {
        return $this->instant->withJdn($this->calendar()->toJdn($this->year(), 1, 1));
    }

    public function endOfYear(): Instant
    {
        $c = $this->components();
        $lastMonth = $this->calendar()->monthsInYear($c['year']);
        $lastDay = $this->calendar()->daysInMonth($c['year'], $lastMonth);
        return $this->instant->withJdn($this->calendar()->toJdn($c['year'], $lastMonth, $lastDay));
    }

    public function diffInMonths(Instant $other): int
    {
        $calendar = $this->calendar();
        [$y1, $m1, $d1] = $calendar->fromJdn($this->instant->jdn);
        [$y2, $m2, $d2] = $calendar->fromJdn($other->jdn);

        $months = ($y1 - $y2) * 12 + ($m1 - $m2);
        // Pull back one month if the day-of-month hasn't been reached yet in the
        // trailing direction, so that diffing (e.g.) 2026-03-15 ↔ 2026-04-14
        // returns 0, not 1.
        if ($months > 0 && $d1 < $d2) {
            $months--;
        } elseif ($months < 0 && $d1 > $d2) {
            $months++;
        }
        return $months;
    }

    // ─── Internals ────────────────────────────────────────────────────

    /**
     * @return array{year:int, month:int, day:int, daysInMonth:int, isLeapYear:bool}
     */
    private function components(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }
        $calendar = $this->calendar();
        [$year, $month, $day] = $calendar->fromJdn($this->instant->jdn);
        return $this->cache = [
            'year' => $year,
            'month' => $month,
            'day' => $day,
            'daysInMonth' => $calendar->daysInMonth($year, $month),
            'isLeapYear' => $calendar->isLeapYear($year),
        ];
    }

}
