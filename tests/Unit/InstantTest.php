<?php

declare(strict_types=1);

namespace Daynum\Tests\Unit;

use Daynum\Calendar\Hijri\HijriCivilCalendar;
use Daynum\Calendar\Hijri\HijriUmmAlQuraCalendar;
use Daynum\Calendar\Hijri\Table;
use Daynum\Calendar\Jalali\JalaliCalendar;
use Daynum\Exception\InvalidDateException;
use Daynum\Exception\WeekAtBoundaryException;
use Daynum\Instant;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

final class InstantTest extends TestCase
{
    public function testFromGregorianStoresJdnAndTime(): void
    {
        $i = Instant::fromGregorian(2026, 4, 8, 14, 30, 45, 'Asia/Tehran');

        $this->assertSame(2461139, $i->jdn);
        $this->assertSame(14 * 3600 + 30 * 60 + 45, $i->secondsOfDay);
        $this->assertSame('Asia/Tehran', $i->tzLabel);
    }

    public function testFromJalaliReturnsSameJdnAsEquivalentGregorian(): void
    {
        $gregorian = Instant::fromGregorian(2026, 4, 8);
        $jalali = Instant::fromJalali(1405, 1, 19);

        $this->assertSame($gregorian->jdn, $jalali->jdn);
    }

    public function testFromDateTimeRoundTripsThroughGregorian(): void
    {
        $dt = new DateTimeImmutable('2026-04-08 14:30:45', new DateTimeZone('UTC'));
        $i = Instant::fromDateTime($dt);

        $this->assertSame(2026, $i->gregorian()->year());
        $this->assertSame(4, $i->gregorian()->month());
        $this->assertSame(8, $i->gregorian()->day());
        $this->assertSame(14, $i->gregorian()->hour());
        $this->assertSame(30, $i->gregorian()->minute());
        $this->assertSame(45, $i->gregorian()->second());
        $this->assertSame('UTC', $i->tzLabel);
    }

    public function testToDateTimeImmutableRoundTripsComponents(): void
    {
        $i = Instant::fromGregorian(2026, 4, 8, 14, 30, 45, 'UTC');
        $dt = $i->toDateTimeImmutable();

        $this->assertSame('2026-04-08 14:30:45', $dt->format('Y-m-d H:i:s'));
        $this->assertSame('UTC', $dt->getTimezone()->getName());
    }

    public function testEqualsComparesJdnAndTimeOfDay(): void
    {
        $a = Instant::fromGregorian(2026, 4, 8, 14, 30);
        $b = Instant::fromGregorian(2026, 4, 8, 14, 30);
        $c = Instant::fromGregorian(2026, 4, 8, 14, 31);

        $this->assertTrue($a->equals($b));
        $this->assertFalse($a->equals($c));
    }

    public function testOrdering(): void
    {
        $earlier = Instant::fromGregorian(2026, 4, 8);
        $later = Instant::fromGregorian(2026, 4, 9);

        $this->assertTrue($earlier->lessThan($later));
        $this->assertTrue($later->greaterThan($earlier));
        $this->assertTrue($earlier->lessThanOrEqual($earlier));
        $this->assertTrue($earlier->greaterThanOrEqual($earlier));
        $this->assertFalse($earlier->greaterThan($later));
    }

    public function testDiffInDaysIsSigned(): void
    {
        $earlier = Instant::fromGregorian(2026, 4, 8);
        $later = Instant::fromGregorian(2026, 4, 10);

        $this->assertSame(2, $later->diffInDays($earlier));
        $this->assertSame(-2, $earlier->diffInDays($later));
    }

    public function testInvalidSecondsOfDayRejected(): void
    {
        $this->expectException(InvalidDateException::class);
        new Instant(0, 86400);
    }

    public function testInvalidTimeComponentsRejected(): void
    {
        $this->expectException(InvalidDateException::class);
        Instant::fromGregorian(2026, 4, 8, 24, 0, 0);
    }

    public function testInvalidGregorianDateRejected(): void
    {
        $this->expectException(InvalidDateException::class);
        Instant::fromGregorian(2026, 2, 30);
    }

    public function testInvalidJalaliMonthRejected(): void
    {
        $this->expectException(InvalidDateException::class);
        Instant::fromJalali(1405, 13, 1);
    }

    public function testJalaliOutOfRangeRejected(): void
    {
        $this->expectException(InvalidDateException::class);
        Instant::fromJalali(4000, 1, 1);
    }

    public function testGregorianViewDayOfYear(): void
    {
        // 2026-04-08: 31 (Jan) + 28 (Feb) + 31 (Mar) + 8 = 98.
        $this->assertSame(
            98,
            Instant::fromGregorian(2026, 4, 8)->gregorian()->dayOfYear()
        );
    }

    public function testJalaliViewDayOfYearHandlesLeapEsfand(): void
    {
        // 1403 is a leap year, so Esfand 30 is valid and is day 366.
        $this->assertSame(
            366,
            Instant::fromJalali(1403, 12, 30)->jalali()->dayOfYear()
        );
    }

    public function testHijriCivilViewDayOfYearInLeapYear(): void
    {
        // AH 2 is leap — Dhu al-Hijjah 30 is the 355th day of the year.
        $this->assertSame(
            355,
            Instant::fromHijriCivil(2, 12, 30)->hijriCivil()->dayOfYear()
        );
    }

    public function testHijriUmmAlQuraViewDayOfYearForRamadan1(): void
    {
        // UAQ AH 1445: Ramadan 1 should equal sum of daysInMonth(1..8) + 1.
        $c = HijriUmmAlQuraCalendar::instance();
        $expected = 1;
        for ($m = 1; $m <= 8; $m++) {
            $expected += $c->daysInMonth(1445, $m);
        }
        $this->assertSame(
            $expected,
            Instant::fromHijri(1445, 9, 1)->hijri()->dayOfYear()
        );
    }

    public function testGregorianFormatZ(): void
    {
        // 2026-04-08 is the 98th day of 2026; PHP-style z is 97.
        $this->assertSame(
            '97',
            Instant::fromGregorian(2026, 4, 8)->gregorian()->format('z')
        );
    }

    public function testJalaliFormatZInLeapYearEsfand(): void
    {
        // 1403 is a leap Jalali year: Esfand 30 is day 366, z = 365.
        $this->assertSame(
            '365',
            Instant::fromJalali(1403, 12, 30)->jalali()->format('z')
        );
    }

    public function testHijriCivilFormatZInLeapYear(): void
    {
        // AH 2 is leap under the tabular civil rule: Dhu al-Hijjah 30 is day
        // 355, z = 354.
        $this->assertSame(
            '354',
            Instant::fromHijriCivil(2, 12, 30)->hijriCivil()->format('z')
        );
    }

    public function testHijriUmmAlQuraFormatZForRamadan1(): void
    {
        // Derive expected z independently of the handler implementation:
        // sum daysInMonth(1445, 1..8), then no +1 and -1 collapse to sum.
        $c = HijriUmmAlQuraCalendar::instance();
        $expected = 0;
        for ($m = 1; $m <= 8; $m++) {
            $expected += $c->daysInMonth(1445, $m);
        }
        $this->assertSame(
            (string) $expected,
            Instant::fromHijri(1445, 9, 1)->hijri()->format('z')
        );
    }

    public function testFormatZBackslashEscape(): void
    {
        // `\z` renders as a literal `z`. Exercises the escape-aware
        // `patternContainsUnescaped` guard threaded through format() —
        // without it, `\z` would still force a dayOfYear() computation.
        $this->assertSame(
            'z',
            Instant::fromGregorian(2026, 4, 8)->gregorian()->format('\z')
        );
    }

    public function testGregorianFormatG(): void
    {
        // Midnight / noon / 1 PM — the load-bearing `% 12 ?: 12` path.
        $this->assertSame('12', Instant::fromGregorian(2026, 4, 8, 0, 0, 0)->gregorian()->format('g'));
        $this->assertSame('12', Instant::fromGregorian(2026, 4, 8, 12, 0, 0)->gregorian()->format('g'));
        $this->assertSame('1',  Instant::fromGregorian(2026, 4, 8, 13, 0, 0)->gregorian()->format('g'));
    }

    public function testGregorianFormatH(): void
    {
        $this->assertSame('12', Instant::fromGregorian(2026, 4, 8, 0, 0, 0)->gregorian()->format('h'));
        $this->assertSame('12', Instant::fromGregorian(2026, 4, 8, 12, 0, 0)->gregorian()->format('h'));
        $this->assertSame('01', Instant::fromGregorian(2026, 4, 8, 13, 0, 0)->gregorian()->format('h'));
    }

    public function testGregorianFormatW(): void
    {
        // 2026-04-08 is Wednesday of ISO week 15 of 2026 (cross-checked with
        // PHP's `date('W', strtotime('2026-04-08'))`).
        $this->assertSame(
            '15',
            Instant::fromGregorian(2026, 4, 8)->gregorian()->format('W')
        );
    }

    public function testJalaliFormatW(): void
    {
        // Non-Gregorian `W` applies the ISO rule to the calendar's own year
        // boundaries. Jalali AP 1405-01-19 (= 2026-04-08) is 18 days into
        // Farvardin 1405, which lands in ISO week 3 of AP 1405 — distinct
        // from Gregorian's week 15 of 2026 even though the JDN is identical.
        $this->assertSame(
            '03',
            Instant::fromJalali(1405, 1, 19)->jalali()->format('W')
        );
    }

    public function testGregorianFormatJSFY(): void
    {
        $this->assertSame(
            '8th April 2026',
            Instant::fromGregorian(2026, 4, 8)->gregorian()->format('jS F Y')
        );
    }

    public function testJalaliFormatJSFYPersian(): void
    {
        // Persian has an empty ordinal suffix, so `jS` renders cleanly as
        // just the day — no `th` residue inside the Perso-Arabic output.
        $this->assertSame(
            '۱۹ فروردین ۱۴۰۵',
            Instant::fromJalali(1405, 1, 19)->jalali()->withLocale('fa')->withDigits('persian')->format('jS F Y')
        );
    }

    public function testWeekOfYearThrowsAtHijriCivilEpoch(): void
    {
        // AH 1 Muharram 1 is Friday (JDN 1948440). Its containing week's
        // Thursday is JDN 1948439 — one day before the epoch.
        $this->expectException(WeekAtBoundaryException::class);
        $this->expectExceptionMessageMatches('/supported year range/');
        Instant::fromHijriCivil(1, 1, 1)->hijriCivil()->weekOfYear();
    }

    public function testHijriCivilFormatWAtEpochBoundaryThrows(): void
    {
        // The `W` format token surfaces the throw from weekOfYear() so
        // `format('W')` at the calendar boundary is a loud error, not a
        // silent collision with the real week 1.
        $this->expectException(WeekAtBoundaryException::class);
        Instant::fromHijriCivil(1, 1, 1)->hijriCivil()->format('W');
    }

    public function testHijriCivilFormatOAtEpochBoundaryThrows(): void
    {
        // `o` has the same boundary behavior as `W` — the containing
        // week's Thursday drops below MIN_YEAR, so there is no valid
        // week-based year to report.
        $this->expectException(WeekAtBoundaryException::class);
        Instant::fromHijriCivil(1, 1, 1)->hijriCivil()->format('o');
    }

    public function testHijriCivilFormatWAtMaxBoundaryThrows(): void
    {
        // AH 9666 Dhu al-Hijjah's last day — MAX_YEAR edge. The
        // containing ISO week's Thursday falls into a notional year
        // above MAX_YEAR; ISO-correct answer would be "week 1 of
        // 9667" but that year isn't a valid HijriCivil year, so throw.
        $cal = HijriCivilCalendar::instance();
        $lastDay = $cal->daysInMonth(HijriCivilCalendar::MAX_YEAR, 12);

        $this->expectException(WeekAtBoundaryException::class);
        Instant::fromHijriCivil(HijriCivilCalendar::MAX_YEAR, 12, $lastDay)
            ->hijriCivil()
            ->format('W');
    }

    public function testHijriCivilFormatWAtNormalNonBoundaryDayNearEpoch(): void
    {
        // AH 1 Muharram 4 is the first Muharram day whose containing
        // ISO week fits entirely inside AH 1 — must render `W=01`
        // without tripping the boundary throw.
        $this->assertSame(
            '01',
            Instant::fromHijriCivil(1, 1, 4)->hijriCivil()->format('W')
        );
    }

    public function testHijriUmmAlQuraFormatWAtMinBoundaryThrows(): void
    {
        // UAQ MIN_YEAR Muharram 1 — UAQ's fromJdn throws
        // UmmAlQuraOutOfRangeException when the Thursday JDN falls below
        // the bundled table's first year. The catch now wraps it in a
        // WeekAtBoundaryException instead of returning a sentinel.
        $this->expectException(WeekAtBoundaryException::class);
        Instant::fromHijri(Table::MIN_YEAR, 1, 1)->hijri()->format('W');
    }

    public function testHijriUmmAlQuraFormatWAtMaxBoundary(): void
    {
        // UAQ MAX_YEAR Dhu al-Hijjah last day — whether the throw fires
        // depends on that specific date's day-of-week. 1600-12-30 falls
        // on a Friday whose containing week's Thursday stays inside the
        // table range, so no throw. Pinning the exact week is fragile
        // across table regenerations; assert only that no throw occurs
        // and the shape is a valid ISO week number.
        $cal = HijriUmmAlQuraCalendar::instance();
        $lastDay = $cal->daysInMonth(Table::MAX_YEAR, 12);
        $w = Instant::fromHijri(Table::MAX_YEAR, 12, $lastDay)
            ->hijri()
            ->format('W');
        $this->assertMatchesRegularExpression('/^(0[1-9]|[1-4][0-9]|5[0-3])$/', $w);
    }

    public function testJalaliFormatWAtMinBoundaryThrows(): void
    {
        // Jalali AP 1-01-01 (= 622 CE). MIN_YEAR edge — the containing
        // week's Thursday falls in notional year 0.
        $this->expectException(WeekAtBoundaryException::class);
        Instant::fromJalali(1, 1, 1)->jalali()->format('W');
    }

    public function testJalaliFormatWAtMaxBoundaryThrows(): void
    {
        // Jalali AP 3177-12-29 — MAX_YEAR edge. The containing ISO
        // week's Thursday spills into a notional year above MAX_YEAR.
        $this->expectException(WeekAtBoundaryException::class);
        Instant::fromJalali(JalaliCalendar::MAX_YEAR, 12, 29)->jalali()->format('W');
    }

    public function testHijriUmmAlQuraFormatWAtMinBoundaryBareDateThrows(): void
    {
        // UAQ MIN boundary — Muharram 1 of Table::MIN_YEAR is a Sunday.
        // The containing week's Thursday is 4 days earlier and falls
        // below the table's first year. Same outcome as the `format`
        // route, exercised via the direct `weekOfYear()` accessor.
        $this->expectException(WeekAtBoundaryException::class);
        Instant::fromHijri(Table::MIN_YEAR, 1, 1)->hijri()->weekOfYear();
    }

    public function testFormatWBackslashEscape(): void
    {
        // `\W` is a literal `W` — tests the benign false positive of the
        // escape-aware `patternContainsUnescaped($pattern, 'W')` guard.
        $this->assertSame(
            'W',
            Instant::fromGregorian(2026, 4, 8)->gregorian()->format('\W')
        );
    }

    public function testFormatWBackslashEscapeSuppressesBoundaryThrow(): void
    {
        // Load-bearing: without the escape-aware guard, `\W` on a date
        // at the boundary would still trip the throw (because a naïve
        // str_contains would see the `W` and call weekOfYear()). The
        // escape-aware helper short-circuits cleanly, so literal `\W`
        // patterns are safe at MIN/MAX edges.
        $this->assertSame(
            'W',
            Instant::fromHijriCivil(1, 1, 1)->hijriCivil()->format('\W')
        );
    }

    public function testFormatOBackslashEscapeSuppressesBoundaryThrow(): void
    {
        // Same guarantee for `\o` on a Jalali MIN-edge date.
        $this->assertSame(
            'o',
            Instant::fromJalali(1, 1, 1)->jalali()->format('\o')
        );
    }

    public function testGregorianFormatOMidYearMatchesYear(): void
    {
        // Mid-year, `o` and `Y` agree — the normal case.
        $this->assertSame(
            '2026',
            Instant::fromGregorian(2026, 4, 8)->gregorian()->format('o')
        );
    }

    public function testGregorianFormatOCrossYearMonBelongsToNextYear(): void
    {
        // 2024-12-30 is a Monday whose ISO week belongs to 2025. `Y-W`
        // would misleadingly render `2024-01`; `o-\WW` correctly
        // renders `2025-W01`.
        $this->assertSame(
            '2025-W01',
            Instant::fromGregorian(2024, 12, 30)->gregorian()->format('o-\WW')
        );
    }

    public function testGregorianFormatOCrossYearSunBelongsToPrevYear(): void
    {
        // 2023-01-01 is a Sunday — ISO week 52 of 2022.
        $this->assertSame(
            '2022-W52',
            Instant::fromGregorian(2023, 1, 1)->gregorian()->format('o-\WW')
        );
    }

    public function testGregorianFormatOAndYDiverge(): void
    {
        // Sanity: on the same date, `o` and `Y` can differ. `oY` emits
        // both adjacent — 2024-12-30 → `o=2025`, `Y=2024`.
        $this->assertSame(
            '20252024',
            Instant::fromGregorian(2024, 12, 30)->gregorian()->format('oY')
        );
    }

    public function testJalaliFormatOMidYear(): void
    {
        // Non-Gregorian `o` applies the ISO Thursday rule to the
        // calendar's own year. Jalali 1405-01-19 mid-year — `o` matches
        // `Y` here since the date is deep inside week 3 of AP 1405.
        $this->assertSame(
            '1405',
            Instant::fromJalali(1405, 1, 19)->jalali()->format('o')
        );
    }


    public function testFormatZAndWCombined(): void
    {
        // Both guarded tokens computed in one call: exercises the two
        // str_contains guards compounding.
        $this->assertSame(
            '97 15',
            Instant::fromGregorian(2026, 4, 8)->gregorian()->format('z W')
        );
    }

    public function testGregorianFormatFullComboSmoke(): void
    {
        // Smoke check from the plan — exercises g, h, i, A, j, S, F, Y, W
        // and backslash escapes all in one pattern.
        $this->assertSame(
            '02:30 PM, 8th April 2026, Week 15',
            Instant::fromGregorian(2026, 4, 8, 14, 30)
                ->gregorian()
                ->format('h:i A, jS F Y, \W\e\e\k W')
        );
    }
}
