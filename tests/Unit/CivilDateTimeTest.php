<?php

declare(strict_types=1);

namespace Eram\Daynum\Tests\Unit;

use Eram\Daynum\Calendar\Gregorian\GregorianCalendar;
use Eram\Daynum\Calendar\Hijri\HijriCivilCalendar;
use Eram\Daynum\Calendar\Hijri\HijriUmmAlQuraCalendar;
use Eram\Daynum\Calendar\Hijri\Table;
use Eram\Daynum\Calendar\Jalali\JalaliCalendar;
use Eram\Daynum\Exception\DaynumException;
use Eram\Daynum\Exception\InvalidArgumentException;
use Eram\Daynum\Exception\InvalidDateException;
use Eram\Daynum\Exception\InvalidTimezoneException;
use Eram\Daynum\Exception\MissingTimezoneException;
use Eram\Daynum\Exception\WeekAtBoundaryException;
use Eram\Daynum\CivilDateTime;
use Eram\Daynum\WeekDay;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

final class CivilDateTimeTest extends TestCase
{
    public function testFromGregorianStoresJdnAndTime(): void
    {
        $i = CivilDateTime::fromGregorian(2026, 4, 8, 14, 30, 45, 'Asia/Tehran');

        $this->assertSame(2461139, $i->jdn);
        $this->assertSame(14 * 3600 + 30 * 60 + 45, $i->secondsOfDay);
        $this->assertSame('Asia/Tehran', $i->tzLabel);
    }

    public function testFromJalaliReturnsSameJdnAsEquivalentGregorian(): void
    {
        $gregorian = CivilDateTime::fromGregorian(2026, 4, 8);
        $jalali = CivilDateTime::fromJalali(1405, 1, 19);

        $this->assertSame($gregorian->jdn, $jalali->jdn);
    }

    public function testFromDateTimeRoundTripsThroughGregorian(): void
    {
        $dt = new DateTimeImmutable('2026-04-08 14:30:45', new DateTimeZone('UTC'));
        $i = CivilDateTime::fromDateTime($dt);

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
        $i = CivilDateTime::fromGregorian(2026, 4, 8, 14, 30, 45, 'UTC');
        $dt = $i->toDateTimeImmutable();

        $this->assertSame('2026-04-08 14:30:45', $dt->format('Y-m-d H:i:s'));
        $this->assertSame('UTC', $dt->getTimezone()->getName());
    }

    public function testEqualsComparesJdnAndTimeOfDay(): void
    {
        $a = CivilDateTime::fromGregorian(2026, 4, 8, 14, 30);
        $b = CivilDateTime::fromGregorian(2026, 4, 8, 14, 30);
        $c = CivilDateTime::fromGregorian(2026, 4, 8, 14, 31);

        $this->assertTrue($a->equals($b));
        $this->assertFalse($a->equals($c));
    }

    public function testOrdering(): void
    {
        $earlier = CivilDateTime::fromGregorian(2026, 4, 8);
        $later = CivilDateTime::fromGregorian(2026, 4, 9);

        $this->assertTrue($earlier->lessThan($later));
        $this->assertTrue($later->greaterThan($earlier));
        $this->assertTrue($earlier->lessThanOrEqual($earlier));
        $this->assertTrue($earlier->greaterThanOrEqual($earlier));
        $this->assertFalse($earlier->greaterThan($later));
    }

    public function testDiffInDaysIsSigned(): void
    {
        $earlier = CivilDateTime::fromGregorian(2026, 4, 8);
        $later = CivilDateTime::fromGregorian(2026, 4, 10);

        $this->assertSame(2, $later->diffInDays($earlier));
        $this->assertSame(-2, $earlier->diffInDays($later));
    }

    public function testInvalidSecondsOfDayRejected(): void
    {
        $this->expectException(InvalidDateException::class);
        new CivilDateTime(0, 86400);
    }

    public function testInvalidTimeComponentsRejected(): void
    {
        $this->expectException(InvalidDateException::class);
        CivilDateTime::fromGregorian(2026, 4, 8, 24, 0, 0);
    }

    public function testInvalidGregorianDateRejected(): void
    {
        $this->expectException(InvalidDateException::class);
        CivilDateTime::fromGregorian(2026, 2, 30);
    }

    public function testInvalidJalaliMonthRejected(): void
    {
        $this->expectException(InvalidDateException::class);
        CivilDateTime::fromJalali(1405, 13, 1);
    }

    public function testJalaliOutOfRangeRejected(): void
    {
        $this->expectException(InvalidDateException::class);
        CivilDateTime::fromJalali(4000, 1, 1);
    }

    public function testGregorianViewDayOfYear(): void
    {
        // 2026-04-08: 31 (Jan) + 28 (Feb) + 31 (Mar) + 8 = 98.
        $this->assertSame(
            98,
            CivilDateTime::fromGregorian(2026, 4, 8)->gregorian()->dayOfYear()
        );
    }

    public function testJalaliViewDayOfYearHandlesLeapEsfand(): void
    {
        // 1403 is a leap year, so Esfand 30 is valid and is day 366.
        $this->assertSame(
            366,
            CivilDateTime::fromJalali(1403, 12, 30)->jalali()->dayOfYear()
        );
    }

    public function testHijriCivilViewDayOfYearInLeapYear(): void
    {
        // AH 2 is leap — Dhu al-Hijjah 30 is the 355th day of the year.
        $this->assertSame(
            355,
            CivilDateTime::fromHijriCivil(2, 12, 30)->hijriCivil()->dayOfYear()
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
            CivilDateTime::fromHijri(1445, 9, 1)->hijri()->dayOfYear()
        );
    }

    public function testGregorianFormatZ(): void
    {
        // 2026-04-08 is the 98th day of 2026; PHP-style z is 97.
        $this->assertSame(
            '97',
            CivilDateTime::fromGregorian(2026, 4, 8)->gregorian()->format('z')
        );
    }

    public function testJalaliFormatZInLeapYearEsfand(): void
    {
        // 1403 is a leap Jalali year: Esfand 30 is day 366, z = 365.
        $this->assertSame(
            '365',
            CivilDateTime::fromJalali(1403, 12, 30)->jalali()->format('z')
        );
    }

    public function testHijriCivilFormatZInLeapYear(): void
    {
        // AH 2 is leap under the tabular civil rule: Dhu al-Hijjah 30 is day
        // 355, z = 354.
        $this->assertSame(
            '354',
            CivilDateTime::fromHijriCivil(2, 12, 30)->hijriCivil()->format('z')
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
            CivilDateTime::fromHijri(1445, 9, 1)->hijri()->format('z')
        );
    }

    public function testFormatZBackslashEscape(): void
    {
        // `\z` renders as a literal `z`. Exercises the escape-aware
        // `patternContainsUnescaped` guard threaded through format() —
        // without it, `\z` would still force a dayOfYear() computation.
        $this->assertSame(
            'z',
            CivilDateTime::fromGregorian(2026, 4, 8)->gregorian()->format('\z')
        );
    }

    public function testGregorianFormatG(): void
    {
        // Midnight / noon / 1 PM — the load-bearing `% 12 ?: 12` path.
        $this->assertSame('12', CivilDateTime::fromGregorian(2026, 4, 8, 0, 0, 0)->gregorian()->format('g'));
        $this->assertSame('12', CivilDateTime::fromGregorian(2026, 4, 8, 12, 0, 0)->gregorian()->format('g'));
        $this->assertSame('1',  CivilDateTime::fromGregorian(2026, 4, 8, 13, 0, 0)->gregorian()->format('g'));
    }

    public function testGregorianFormatH(): void
    {
        $this->assertSame('12', CivilDateTime::fromGregorian(2026, 4, 8, 0, 0, 0)->gregorian()->format('h'));
        $this->assertSame('12', CivilDateTime::fromGregorian(2026, 4, 8, 12, 0, 0)->gregorian()->format('h'));
        $this->assertSame('01', CivilDateTime::fromGregorian(2026, 4, 8, 13, 0, 0)->gregorian()->format('h'));
    }

    public function testGregorianFormatW(): void
    {
        // 2026-04-08 is Wednesday of ISO week 15 of 2026 (cross-checked with
        // PHP's `date('W', strtotime('2026-04-08'))`).
        $this->assertSame(
            '15',
            CivilDateTime::fromGregorian(2026, 4, 8)->gregorian()->format('W')
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
            CivilDateTime::fromJalali(1405, 1, 19)->jalali()->format('W')
        );
    }

    public function testGregorianFormatJSFY(): void
    {
        $this->assertSame(
            '8th April 2026',
            CivilDateTime::fromGregorian(2026, 4, 8)->gregorian()->format('jS F Y')
        );
    }

    public function testJalaliFormatJSFYPersian(): void
    {
        // Persian has an empty ordinal suffix, so `jS` renders cleanly as
        // just the day — no `th` residue inside the Perso-Arabic output.
        $this->assertSame(
            '۱۹ فروردین ۱۴۰۵',
            CivilDateTime::fromJalali(1405, 1, 19)->jalali()->withLocale('fa')->withDigits('persian')->format('jS F Y')
        );
    }

    public function testWeekOfYearThrowsAtHijriCivilEpoch(): void
    {
        // AH 1 Muharram 1 is Friday (JDN 1948440). Its containing week's
        // Thursday is JDN 1948439 — one day before the epoch.
        $this->expectException(WeekAtBoundaryException::class);
        $this->expectExceptionMessageMatches('/supported year range/');
        CivilDateTime::fromHijriCivil(1, 1, 1)->hijriCivil()->weekOfYear();
    }

    public function testHijriCivilFormatWAtEpochBoundaryThrows(): void
    {
        // The `W` format token surfaces the throw from weekOfYear() so
        // `format('W')` at the calendar boundary is a loud error, not a
        // silent collision with the real week 1.
        $this->expectException(WeekAtBoundaryException::class);
        CivilDateTime::fromHijriCivil(1, 1, 1)->hijriCivil()->format('W');
    }

    public function testHijriCivilFormatOAtEpochBoundaryThrows(): void
    {
        // `o` has the same boundary behavior as `W` — the containing
        // week's Thursday drops below MIN_YEAR, so there is no valid
        // week-based year to report.
        $this->expectException(WeekAtBoundaryException::class);
        CivilDateTime::fromHijriCivil(1, 1, 1)->hijriCivil()->format('o');
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
        CivilDateTime::fromHijriCivil(HijriCivilCalendar::MAX_YEAR, 12, $lastDay)
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
            CivilDateTime::fromHijriCivil(1, 1, 4)->hijriCivil()->format('W')
        );
    }

    public function testHijriUmmAlQuraFormatWAtMinBoundaryThrows(): void
    {
        // UAQ MIN_YEAR Muharram 1 — UAQ's fromJdn throws
        // UmmAlQuraOutOfRangeException when the Thursday JDN falls below
        // the bundled table's first year. The catch now wraps it in a
        // WeekAtBoundaryException instead of returning a sentinel.
        $this->expectException(WeekAtBoundaryException::class);
        CivilDateTime::fromHijri(Table::MIN_YEAR, 1, 1)->hijri()->format('W');
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
        $w = CivilDateTime::fromHijri(Table::MAX_YEAR, 12, $lastDay)
            ->hijri()
            ->format('W');
        $this->assertMatchesRegularExpression('/^(0[1-9]|[1-4][0-9]|5[0-3])$/', $w);
    }

    public function testJalaliFormatWAtMinBoundaryThrows(): void
    {
        // Jalali AP 1-01-01 (= 622 CE). MIN_YEAR edge — the containing
        // week's Thursday falls in notional year 0.
        $this->expectException(WeekAtBoundaryException::class);
        CivilDateTime::fromJalali(1, 1, 1)->jalali()->format('W');
    }

    public function testJalaliFormatWAtMaxBoundaryThrows(): void
    {
        // Jalali AP 3177-12-29 — MAX_YEAR edge. The containing ISO
        // week's Thursday spills into a notional year above MAX_YEAR.
        $this->expectException(WeekAtBoundaryException::class);
        CivilDateTime::fromJalali(JalaliCalendar::MAX_YEAR, 12, 29)->jalali()->format('W');
    }

    public function testHijriUmmAlQuraFormatWAtMinBoundaryBareDateThrows(): void
    {
        // UAQ MIN boundary — Muharram 1 of Table::MIN_YEAR is a Sunday.
        // The containing week's Thursday is 4 days earlier and falls
        // below the table's first year. Same outcome as the `format`
        // route, exercised via the direct `weekOfYear()` accessor.
        $this->expectException(WeekAtBoundaryException::class);
        CivilDateTime::fromHijri(Table::MIN_YEAR, 1, 1)->hijri()->weekOfYear();
    }

    public function testFormatWBackslashEscape(): void
    {
        // `\W` is a literal `W` — tests the benign false positive of the
        // escape-aware `patternContainsUnescaped($pattern, 'W')` guard.
        $this->assertSame(
            'W',
            CivilDateTime::fromGregorian(2026, 4, 8)->gregorian()->format('\W')
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
            CivilDateTime::fromHijriCivil(1, 1, 1)->hijriCivil()->format('\W')
        );
    }

    public function testFormatOBackslashEscapeSuppressesBoundaryThrow(): void
    {
        // Same guarantee for `\o` on a Jalali MIN-edge date.
        $this->assertSame(
            'o',
            CivilDateTime::fromJalali(1, 1, 1)->jalali()->format('\o')
        );
    }

    public function testGregorianFormatOMidYearMatchesYear(): void
    {
        // Mid-year, `o` and `Y` agree — the normal case.
        $this->assertSame(
            '2026',
            CivilDateTime::fromGregorian(2026, 4, 8)->gregorian()->format('o')
        );
    }

    public function testGregorianFormatOCrossYearMonBelongsToNextYear(): void
    {
        // 2024-12-30 is a Monday whose ISO week belongs to 2025. `Y-W`
        // would misleadingly render `2024-01`; `o-\WW` correctly
        // renders `2025-W01`.
        $this->assertSame(
            '2025-W01',
            CivilDateTime::fromGregorian(2024, 12, 30)->gregorian()->format('o-\WW')
        );
    }

    public function testGregorianFormatOCrossYearSunBelongsToPrevYear(): void
    {
        // 2023-01-01 is a Sunday — ISO week 52 of 2022.
        $this->assertSame(
            '2022-W52',
            CivilDateTime::fromGregorian(2023, 1, 1)->gregorian()->format('o-\WW')
        );
    }

    public function testGregorianFormatOAndYDiverge(): void
    {
        // Sanity: on the same date, `o` and `Y` can differ. `oY` emits
        // both adjacent — 2024-12-30 → `o=2025`, `Y=2024`.
        $this->assertSame(
            '20252024',
            CivilDateTime::fromGregorian(2024, 12, 30)->gregorian()->format('oY')
        );
    }

    public function testJalaliFormatOMidYear(): void
    {
        // Non-Gregorian `o` applies the ISO Thursday rule to the
        // calendar's own year. Jalali 1405-01-19 mid-year — `o` matches
        // `Y` here since the date is deep inside week 3 of AP 1405.
        $this->assertSame(
            '1405',
            CivilDateTime::fromJalali(1405, 1, 19)->jalali()->format('o')
        );
    }


    public function testFormatZAndWCombined(): void
    {
        // Both guarded tokens computed in one call: exercises the two
        // str_contains guards compounding.
        $this->assertSame(
            '97 15',
            CivilDateTime::fromGregorian(2026, 4, 8)->gregorian()->format('z W')
        );
    }

    public function testGregorianFormatFullComboSmoke(): void
    {
        // Smoke check from the plan — exercises g, h, i, A, j, S, F, Y, W
        // and backslash escapes all in one pattern.
        $this->assertSame(
            '02:30 PM, 8th April 2026, Week 15',
            CivilDateTime::fromGregorian(2026, 4, 8, 14, 30)
                ->gregorian()
                ->format('h:i A, jS F Y, \W\e\e\k W')
        );
    }

    // ─── now() ───────────────────────────────────────────────

    public function testNowReturnsCurrentDateWithResolvedTimezone(): void
    {
        $before = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $dateTime = CivilDateTime::now('UTC');
        $after = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        $g = $dateTime->gregorian();
        $this->assertSame((int) $before->format('Y'), $g->year());
        $this->assertSame('UTC', $dateTime->tzLabel);
        // secondsOfDay should be non-negative (it always is, but confirms time is captured)
        $this->assertGreaterThanOrEqual(0, $dateTime->secondsOfDay);
    }

    public function testNowWithoutTimezoneResolvesDefault(): void
    {
        $oldTz = date_default_timezone_get();
        date_default_timezone_set('Asia/Tehran');
        try {
            $dateTime = CivilDateTime::now();
            $this->assertSame('Asia/Tehran', $dateTime->tzLabel);
        } finally {
            date_default_timezone_set($oldTz);
        }
    }

    public function testNowCapturesTimeOfDay(): void
    {
        $dateTime = CivilDateTime::now('UTC');
        // now() should capture current time, not midnight
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $expectedSeconds = (int) $now->format('G') * 3600
                         + (int) $now->format('i') * 60
                         + (int) $now->format('s');
        // Allow 2 seconds of drift between the two calls
        $this->assertEqualsWithDelta($expectedSeconds, $dateTime->secondsOfDay, 2);
    }

    public function testTodayResolvesDefaultTimezone(): void
    {
        $oldTz = date_default_timezone_get();
        date_default_timezone_set('Europe/London');
        try {
            $dateTime = CivilDateTime::today();
            $this->assertSame('Europe/London', $dateTime->tzLabel);
            $this->assertSame(0, $dateTime->secondsOfDay);
        } finally {
            date_default_timezone_set($oldTz);
        }
    }

    public function testTodayWithExplicitTimezoneStoresIt(): void
    {
        $dateTime = CivilDateTime::today('UTC');
        $this->assertSame('UTC', $dateTime->tzLabel);
        $this->assertSame(0, $dateTime->secondsOfDay);
    }

    // ─── tomorrow() / yesterday() ──────────────────────────────────

    public function testTomorrowIsOneDayAfterToday(): void
    {
        $this->assertSame(
            CivilDateTime::today('UTC')->jdn + 1,
            CivilDateTime::tomorrow('UTC')->jdn,
        );
    }

    public function testYesterdayIsOneDayBeforeToday(): void
    {
        $this->assertSame(
            CivilDateTime::today('UTC')->jdn - 1,
            CivilDateTime::yesterday('UTC')->jdn,
        );
    }

    public function testTomorrowIsMidnight(): void
    {
        $this->assertSame(0, CivilDateTime::tomorrow()->secondsOfDay);
    }

    public function testYesterdayIsMidnight(): void
    {
        $this->assertSame(0, CivilDateTime::yesterday()->secondsOfDay);
    }

    public function testTomorrowResolvesTimezone(): void
    {
        $this->assertSame('Asia/Tehran', CivilDateTime::tomorrow('Asia/Tehran')->tzLabel);
    }

    public function testYesterdayResolvesDefaultTimezone(): void
    {
        $oldTz = date_default_timezone_get();
        date_default_timezone_set('America/New_York');
        try {
            $this->assertSame('America/New_York', CivilDateTime::yesterday()->tzLabel);
        } finally {
            date_default_timezone_set($oldTz);
        }
    }

    // ─── JsonSerializable + fromArray ────────────────────────────────

    public function testJsonSerializeProducesCalendarNeutralArray(): void
    {
        $i = CivilDateTime::fromGregorian(2026, 4, 8, 14, 30, 45, 'Asia/Tehran');
        $data = $i->jsonSerialize();

        $this->assertSame($i->jdn, $data['jdn']);
        $this->assertSame($i->secondsOfDay, $data['secondsOfDay']);
        $this->assertSame('Asia/Tehran', $data['tzLabel']);
    }

    public function testJsonEncodeProducesExpectedJson(): void
    {
        $i = CivilDateTime::fromGregorian(2026, 4, 8, 0, 0, 0, 'UTC');
        $json = json_encode($i);
        $this->assertNotFalse($json);
        $decoded = json_decode($json, true);

        $this->assertSame($i->jdn, $decoded['jdn']);
        $this->assertSame(0, $decoded['secondsOfDay']);
        $this->assertSame('UTC', $decoded['tzLabel']);
    }

    public function testJsonSerializeWithNullTzLabel(): void
    {
        $i = CivilDateTime::fromGregorian(2026, 4, 8);
        $data = $i->jsonSerialize();

        $this->assertNull($data['tzLabel']);
        // Verify JSON encodes null correctly
        $json = json_encode($i);
        $this->assertNotFalse($json);
        $this->assertStringContainsString('"tzLabel":null', $json);
    }

    public function testFromArrayRoundTrips(): void
    {
        $original = CivilDateTime::fromGregorian(2026, 4, 8, 14, 30, 45, 'Asia/Tehran');
        $restored = CivilDateTime::fromArray($original->jsonSerialize());

        $this->assertTrue($original->equals($restored));
        $this->assertSame($original->tzLabel, $restored->tzLabel);
    }

    public function testFromArrayWithMinimalData(): void
    {
        $i = CivilDateTime::fromArray(['jdn' => 2461139]);
        $this->assertSame(2461139, $i->jdn);
        $this->assertSame(0, $i->secondsOfDay);
        $this->assertNull($i->tzLabel);
    }

    public function testFromArrayRejectsInvalidInput(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        CivilDateTime::fromArray(['secondsOfDay' => 0]);
    }

    public function testFromArrayJsonRoundTrip(): void
    {
        $original = CivilDateTime::fromJalali(1405, 1, 19, 8, 15, 0, 'Asia/Tehran');
        $json = json_encode($original);
        $this->assertNotFalse($json);
        $restored = CivilDateTime::fromArray(json_decode($json, true));

        $this->assertTrue($original->equals($restored));
        $this->assertSame($original->tzLabel, $restored->tzLabel);
    }

    public function testFromArrayRejectsStringSecondsOfDay(): void
    {
        $this->expectException(InvalidArgumentException::class);
        CivilDateTime::fromArray(['jdn' => 2461139, 'secondsOfDay' => '0']);
    }

    public function testFromArrayRejectsFloatSecondsOfDay(): void
    {
        $this->expectException(InvalidArgumentException::class);
        CivilDateTime::fromArray(['jdn' => 2461139, 'secondsOfDay' => 0.0]);
    }

    public function testFromArrayRejectsBoolSecondsOfDay(): void
    {
        $this->expectException(InvalidArgumentException::class);
        CivilDateTime::fromArray(['jdn' => 2461139, 'secondsOfDay' => true]);
    }

    public function testFromArrayRejectsNonStringTzLabel(): void
    {
        $this->expectException(InvalidArgumentException::class);
        CivilDateTime::fromArray(['jdn' => 2461139, 'tzLabel' => 123]);
    }

    public function testFromArrayRejectsArrayTzLabel(): void
    {
        $this->expectException(InvalidArgumentException::class);
        CivilDateTime::fromArray(['jdn' => 2461139, 'tzLabel' => ['UTC']]);
    }

    public function testFromArrayRejectsStringJdn(): void
    {
        $this->expectException(InvalidArgumentException::class);
        CivilDateTime::fromArray(['jdn' => '2461139']);
    }

    public function testFromArrayAcceptsExplicitNullTzLabel(): void
    {
        $i = CivilDateTime::fromArray(['jdn' => 2461139, 'tzLabel' => null]);
        $this->assertSame(2461139, $i->jdn);
        $this->assertNull($i->tzLabel);
    }

    public function testFromArrayAcceptsExplicitNullSecondsOfDay(): void
    {
        $i = CivilDateTime::fromArray(['jdn' => 2461139, 'secondsOfDay' => null]);
        $this->assertSame(2461139, $i->jdn);
        $this->assertSame(0, $i->secondsOfDay);
    }

    public function testFromArrayIgnoresExtraKeys(): void
    {
        $i = CivilDateTime::fromArray(['jdn' => 2461139, 'extra' => 'junk', 'secondsOfDay' => 3600]);
        $this->assertSame(2461139, $i->jdn);
        $this->assertSame(3600, $i->secondsOfDay);
    }

    public function testFromArrayAcceptsBoundarySecondsOfDay(): void
    {
        $lo = CivilDateTime::fromArray(['jdn' => 2461139, 'secondsOfDay' => 0]);
        $hi = CivilDateTime::fromArray(['jdn' => 2461139, 'secondsOfDay' => 86399]);
        $this->assertSame(0, $lo->secondsOfDay);
        $this->assertSame(86399, $hi->secondsOfDay);
    }

    public function testFromArrayRejectsSecondsOfDayAt86400(): void
    {
        $this->expectException(DaynumException::class);
        CivilDateTime::fromArray(['jdn' => 2461139, 'secondsOfDay' => 86400]);
    }

    // ─── View toArray() ──────────────────────────────────────────────

    public function testGregorianViewToArray(): void
    {
        $i = CivilDateTime::fromGregorian(2026, 4, 8, 14, 30, 45, 'UTC');
        $arr = $i->gregorian()->toArray();

        $this->assertSame(2026, $arr['year']);
        $this->assertSame(4, $arr['month']);
        $this->assertSame(8, $arr['day']);
        $this->assertSame(14, $arr['hour']);
        $this->assertSame(30, $arr['minute']);
        $this->assertSame(45, $arr['second']);
        $this->assertSame('UTC', $arr['tzLabel']);
    }

    public function testJalaliViewToArray(): void
    {
        $i = CivilDateTime::fromJalali(1405, 1, 19, 14, 30, 0, 'Asia/Tehran');
        $arr = $i->jalali()->toArray();

        $this->assertSame(1405, $arr['year']);
        $this->assertSame(1, $arr['month']);
        $this->assertSame(19, $arr['day']);
        $this->assertSame(14, $arr['hour']);
        $this->assertSame(30, $arr['minute']);
        $this->assertSame(0, $arr['second']);
        $this->assertSame('Asia/Tehran', $arr['tzLabel']);
    }

    public function testHijriViewToArray(): void
    {
        $i = CivilDateTime::fromHijri(1447, 10, 21);
        $arr = $i->hijri()->toArray();

        $this->assertSame(1447, $arr['year']);
        $this->assertSame(10, $arr['month']);
        $this->assertSame(21, $arr['day']);
    }

    public function testViewToArrayWithNullTimezone(): void
    {
        $i = CivilDateTime::fromGregorian(2026, 4, 8);
        $arr = $i->gregorian()->toArray();

        $this->assertNull($arr['tzLabel']);
    }

    // ─── tryFrom* ────────────────────────────────────────────────────

    public function testTryFromGregorianReturnsCivilDateTimeForValidDate(): void
    {
        $i = CivilDateTime::tryFromGregorian(2026, 4, 8, 14, 30);
        $this->assertNotNull($i);
        $this->assertSame(2026, $i->gregorian()->year());
    }

    public function testTryFromGregorianReturnsNullForInvalidDate(): void
    {
        $this->assertNull(CivilDateTime::tryFromGregorian(2026, 2, 30));
        $this->assertNull(CivilDateTime::tryFromGregorian(2026, 13, 1));
    }

    public function testTryFromGregorianReturnsNullForInvalidTime(): void
    {
        $this->assertNull(CivilDateTime::tryFromGregorian(2026, 4, 8, 24, 0, 0));
    }

    public function testTryFromJalaliReturnsCivilDateTimeForValidDate(): void
    {
        $i = CivilDateTime::tryFromJalali(1405, 1, 19);
        $this->assertNotNull($i);
        $this->assertSame(1405, $i->jalali()->year());
    }

    public function testTryFromJalaliReturnsNullForInvalidDate(): void
    {
        $this->assertNull(CivilDateTime::tryFromJalali(1405, 13, 1));
        $this->assertNull(CivilDateTime::tryFromJalali(4000, 1, 1));
    }

    public function testTryFromHijriReturnsCivilDateTimeForValidDate(): void
    {
        $i = CivilDateTime::tryFromHijri(1447, 10, 21);
        $this->assertNotNull($i);
        $this->assertSame(1447, $i->hijri()->year());
    }

    public function testTryFromHijriReturnsNullForInvalidDate(): void
    {
        $this->assertNull(CivilDateTime::tryFromHijri(1447, 13, 1));
    }

    public function testTryFromHijriReturnsNullForOutOfRangeYear(): void
    {
        // Catches UmmAlQuraOutOfRangeException, not just InvalidDateException
        $this->assertNull(CivilDateTime::tryFromHijri(1200, 1, 1));
        $this->assertNull(CivilDateTime::tryFromHijri(1700, 1, 1));
    }

    public function testTryFromHijriAcceptsBoundaryDates(): void
    {
        // Table boundaries are inclusive
        $this->assertNotNull(CivilDateTime::tryFromHijri(Table::MIN_YEAR, 1, 1));
        $this->assertNotNull(CivilDateTime::tryFromHijri(Table::MAX_YEAR, 12, 29));
    }

    public function testTryFromHijriCivilReturnsCivilDateTimeForValidDate(): void
    {
        $i = CivilDateTime::tryFromHijriCivil(1447, 10, 21);
        $this->assertNotNull($i);
    }

    public function testTryFromHijriCivilReturnsNullForInvalidDate(): void
    {
        $this->assertNull(CivilDateTime::tryFromHijriCivil(1447, 13, 1));
    }

    // ─── isValid* ────────────────────────────────────────────────────

    public function testIsValidGregorian(): void
    {
        $this->assertTrue(CivilDateTime::isValidGregorian(2026, 4, 8));
        $this->assertTrue(CivilDateTime::isValidGregorian(2024, 2, 29)); // leap year
        $this->assertFalse(CivilDateTime::isValidGregorian(2026, 2, 29));
        $this->assertFalse(CivilDateTime::isValidGregorian(2026, 0, 1));
    }

    public function testIsValidJalali(): void
    {
        $this->assertTrue(CivilDateTime::isValidJalali(1405, 1, 19));
        $this->assertTrue(CivilDateTime::isValidJalali(1403, 12, 30)); // leap year
        $this->assertFalse(CivilDateTime::isValidJalali(1405, 12, 30));
        $this->assertFalse(CivilDateTime::isValidJalali(1405, 13, 1));
    }

    public function testIsValidHijri(): void
    {
        $this->assertTrue(CivilDateTime::isValidHijri(1447, 10, 21));
        $this->assertFalse(CivilDateTime::isValidHijri(1200, 1, 1)); // out of range
        $this->assertFalse(CivilDateTime::isValidHijri(1447, 1, 31)); // no month has 31 days
    }

    public function testIsValidHijriCivil(): void
    {
        $this->assertTrue(CivilDateTime::isValidHijriCivil(1447, 10, 21));
        $this->assertFalse(CivilDateTime::isValidHijriCivil(1447, 13, 1));
    }

    // ─── supportsYear() ─────────────────────────────────────────────

    public function testGregorianSupportsYear(): void
    {
        $cal = GregorianCalendar::instance();
        $this->assertTrue($cal->supportsYear(0));         // year 0 exists in proleptic Gregorian
        $this->assertTrue($cal->supportsYear(-9999));
        $this->assertTrue($cal->supportsYear(9999));
        $this->assertFalse($cal->supportsYear(-10000));
        $this->assertFalse($cal->supportsYear(10000));
    }

    public function testJalaliSupportsYear(): void
    {
        $cal = JalaliCalendar::instance();
        $this->assertTrue($cal->supportsYear(1));
        $this->assertTrue($cal->supportsYear(3177));
        $this->assertFalse($cal->supportsYear(0));
        $this->assertFalse($cal->supportsYear(3178));
    }

    public function testHijriCivilSupportsYear(): void
    {
        $cal = HijriCivilCalendar::instance();
        $this->assertTrue($cal->supportsYear(1));
        $this->assertTrue($cal->supportsYear(9666));
        $this->assertFalse($cal->supportsYear(0));
        $this->assertFalse($cal->supportsYear(9667));
    }

    public function testHijriUaqSupportsYear(): void
    {
        $cal = HijriUmmAlQuraCalendar::instance();
        $this->assertTrue($cal->supportsYear(1300));
        $this->assertTrue($cal->supportsYear(1600));
        $this->assertFalse($cal->supportsYear(1299));
        $this->assertFalse($cal->supportsYear(1601));
    }

    // ─── isInSupportedRange() ───────────────────────────────────────

    public function testIsInSupportedRangeNormalDates(): void
    {
        $this->assertTrue(CivilDateTime::fromGregorian(2026, 4, 8)->gregorian()->isInSupportedRange());
        $this->assertTrue(CivilDateTime::fromJalali(1405, 1, 19)->jalali()->isInSupportedRange());
        $this->assertTrue(CivilDateTime::fromHijri(1447, 10, 21)->hijri()->isInSupportedRange());
    }

    public function testIsInSupportedRangeOutOfRangeUaq(): void
    {
        // A JDN before the UAQ table's first year
        $uaqMinJdn = Table::YEAR_STARTS[Table::MIN_YEAR];
        $i = new CivilDateTime($uaqMinJdn - 1);
        $this->assertFalse($i->hijri()->isInSupportedRange());
    }

    // ─── startOfWeek() / endOfWeek() ────────────────────────────────

    public function testStartOfWeekMondayStart(): void
    {
        // 2026-04-08 is Wednesday → Monday start = Apr 6
        $i = CivilDateTime::fromGregorian(2026, 4, 8, 14, 30);
        $start = $i->gregorian()->startOfWeek(1);
        $g = $start->gregorian();
        $this->assertSame(2026, $g->year());
        $this->assertSame(4, $g->month());
        $this->assertSame(6, $g->day());
        // Preserves time
        $this->assertSame(14, $g->hour());
        $this->assertSame(30, $g->minute());
    }

    public function testEndOfWeekMondayStart(): void
    {
        // 2026-04-08 is Wednesday → Sunday end = Apr 12
        $i = CivilDateTime::fromGregorian(2026, 4, 8, 14, 30);
        $end = $i->gregorian()->endOfWeek(1);
        $g = $end->gregorian();
        $this->assertSame(2026, $g->year());
        $this->assertSame(4, $g->month());
        $this->assertSame(12, $g->day());
        // Preserves time
        $this->assertSame(14, $g->hour());
    }

    public function testStartOfWeekSaturdayStart(): void
    {
        // 2026-04-08 is Wednesday, Saturday start → Saturday Apr 4
        $i = CivilDateTime::fromGregorian(2026, 4, 8);
        $start = $i->gregorian()->startOfWeek(6);
        $g = $start->gregorian();
        $this->assertSame(4, $g->month());
        $this->assertSame(4, $g->day());
    }

    public function testEndOfWeekSundayStart(): void
    {
        // 2026-04-08 is Wednesday, Sunday start → end is Saturday Apr 11
        $i = CivilDateTime::fromGregorian(2026, 4, 8);
        $end = $i->gregorian()->endOfWeek(7);
        $g = $end->gregorian();
        $this->assertSame(4, $g->month());
        $this->assertSame(11, $g->day());
    }

    public function testStartOfWeekOnStartDay(): void
    {
        // 2026-04-06 is Monday, weekStart=1 → same JDN
        $i = CivilDateTime::fromGregorian(2026, 4, 6);
        $start = $i->gregorian()->startOfWeek(1);
        $this->assertSame($i->jdn, $start->jdn);
    }

    public function testStartOfWeekPreservesTzLabel(): void
    {
        $i = CivilDateTime::fromGregorian(2026, 4, 8, 14, 30, 0, 'Asia/Tehran');
        $start = $i->gregorian()->startOfWeek(1);
        $this->assertSame('Asia/Tehran', $start->tzLabel);
    }

    public function testStartOfWeekInvalidWeekStartThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        CivilDateTime::fromGregorian(2026, 4, 8)->gregorian()->startOfWeek(0);
    }

    public function testEndOfWeekInvalidWeekStartThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        CivilDateTime::fromGregorian(2026, 4, 8)->gregorian()->endOfWeek(8);
    }

    public function testJalaliStartOfWeekSaturdayStart(): void
    {
        // Iranian convention: Saturday start
        $i = CivilDateTime::fromJalali(1405, 1, 19); // = 2026-04-08 Wednesday
        $start = $i->jalali()->startOfWeek(6);
        $j = $start->jalali();
        // Saturday start from Wednesday: goes back to Jalali 1405/01/16
        $this->assertSame(1405, $j->year());
        $this->assertSame(1, $j->month());
        $this->assertSame(15, $j->day());
    }

    // ─── Locale-aware week start and weekend ────────────────────────

    /**
     * @return iterable<string, array{string, WeekDay, list<WeekDay>}>
     */
    public static function localeWeekProvider(): iterable
    {
        yield 'en' => ['en', WeekDay::Monday, [WeekDay::Saturday, WeekDay::Sunday]];
        yield 'fa' => ['fa', WeekDay::Saturday, [WeekDay::Friday]];
        yield 'ar' => ['ar', WeekDay::Sunday, [WeekDay::Friday, WeekDay::Saturday]];
    }

    /**
     * @param list<WeekDay> $weekend
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('localeWeekProvider')]
    public function testDefaultWeekStartAndWeekendFollowLocale(string $tag, WeekDay $first, array $weekend): void
    {
        // 2026-04-06 is a Monday; walk one full week in every calendar view.
        $monday = CivilDateTime::fromGregorian(2026, 4, 6);
        foreach (['gregorian', 'jalali', 'hijri', 'hijriCivil'] as $cal) {
            for ($i = 0; $i < 7; $i++) {
                $view = $monday->addDays($i)->{$cal}()->withLocale($tag);
                $start = $view->startOfWeek();
                $this->assertSame($first, $start->{$cal}()->weekDay(), "{$tag} {$cal} +{$i}");
                $this->assertLessThan(7, $view->dateTime()->diffInDays($start));
                $this->assertSame(6, $view->endOfWeek()->diffInDays($start));
                $this->assertSame(in_array($view->weekDay(), $weekend, true), $view->isWeekend(), "{$tag} {$cal} +{$i}");
                $this->assertSame(!$view->isWeekend(), $view->isWeekday());
            }
        }
    }

    public function testExplicitWeekStartOverridesLocale(): void
    {
        $wed = CivilDateTime::fromGregorian(2026, 4, 8)->jalali()->withLocale('fa');
        $this->assertSame('1405/01/15', $wed->startOfWeek()->jalali()->format('Y/m/d'));          // Saturday
        $this->assertSame('1405/01/17', $wed->startOfWeek(WeekDay::Monday)->jalali()->format('Y/m/d'));
        $this->assertSame('1405/01/21', $wed->endOfWeek()->jalali()->format('Y/m/d'));            // Friday
    }

    public function testWeekDayAccessor(): void
    {
        $this->assertSame(WeekDay::Wednesday, CivilDateTime::fromGregorian(2026, 4, 8)->gregorian()->weekDay());
        $this->assertSame(WeekDay::Friday, CivilDateTime::fromJalali(1405, 1, 21)->jalali()->weekDay());
    }

    // ─── Relative time ──────────────────────────────────────────────

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function relativeTimeProvider(): iterable
    {
        yield 'same moment'     => [0, 'now'];
        yield '59 seconds'      => [-59, '59 seconds ago'];
        yield '60 seconds'      => [-60, '1 minute ago'];
        yield '1h 59m'          => [-7199, '1 hour ago'];
        yield '23h 59m 59s'     => [-86399, '23 hours ago'];
        yield '6 days 23h'      => [-(7 * 86400 - 1), '6 days ago'];
        yield '13 days'         => [-13 * 86400, '1 week ago'];
        yield '27 days'         => [-27 * 86400, '3 weeks ago'];
        yield 'future 2 hours'  => [7200, 'in 2 hours'];
        yield 'future 1 second' => [1, 'in 1 second'];
        yield '400 days'        => [-400 * 86400, '1 year ago'];
        yield 'future 3 years'  => [3 * 366 * 86400, 'in 3 years'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('relativeTimeProvider')]
    public function testDiffForHumansPicksLargestWholeUnit(int $offsetSeconds, string $expected): void
    {
        $now = CivilDateTime::fromGregorian(2026, 4, 8, 12, 0, 0, 'UTC');
        $this->assertSame($expected, $now->addSeconds($offsetSeconds)->gregorian()->diffForHumans($now));
    }

    public function testDiffForHumansCountsMonthsInTheViewCalendar(): void
    {
        // 29 days: one whole Hijri month (Shawwal 1447 has 29 days), but
        // not yet a whole Gregorian month (Apr 8 → May 7).
        $now = CivilDateTime::fromGregorian(2026, 4, 8);
        $later = $now->addDays(29);
        $this->assertSame('in 4 weeks', $later->gregorian()->diffForHumans($now));
        $this->assertSame('in 1 month', $later->hijri()->diffForHumans($now));
    }

    public function testDiffForHumansWholeYearAndMonthNeedTimeOfDay(): void
    {
        $a = CivilDateTime::fromGregorian(2025, 1, 1, 23, 59, 59);
        $b = CivilDateTime::fromGregorian(2026, 1, 1);
        $this->assertSame('in 11 months', $b->gregorian()->diffForHumans($a));
        $this->assertSame('11 months ago', $a->gregorian()->diffForHumans($b));

        $c = CivilDateTime::fromGregorian(2026, 2, 1, 0, 30);
        $d = CivilDateTime::fromGregorian(2026, 3, 1);
        $this->assertSame('in 3 weeks', $d->gregorian()->diffForHumans($c));
        $this->assertSame('in 1 month', $d->addMinutes(30)->gregorian()->diffForHumans($c));
    }

    public function testDiffForHumansLocalesAndDigits(): void
    {
        $now = CivilDateTime::fromJalali(1405, 1, 19);
        $past = $now->subDays(3);
        $future = $now->addDays(2);

        $this->assertSame('۳ روز پیش', $past->jalali()->withLocale('fa')->withDigits('persian')->diffForHumans($now));
        $this->assertSame('3 روز پیش', $past->jalali()->withLocale('fa')->diffForHumans($now));
        $this->assertSame('۲ روز دیگر', $future->jalali()->withLocale('fa')->withDigits('persian')->diffForHumans($now));
        $this->assertSame('قبل ٣ أيام', $past->hijri()->withLocale('ar')->withDigits('arab')->diffForHumans($now));
        $this->assertSame('خلال يومين', $future->hijri()->withLocale('ar')->diffForHumans($now));
        $this->assertSame('قبل ١١ ساعة', $now->subHours(11)->hijri()->withLocale('ar')->withDigits('arab')->diffForHumans($now));
    }

    public function testDiffForHumansNowInEveryLocale(): void
    {
        $d = CivilDateTime::fromJalali(1405, 1, 19, 12, 0, 0);
        $this->assertSame('اکنون', $d->jalali()->withLocale('fa')->diffForHumans($d));
        $this->assertSame('الآن', $d->hijri()->withLocale('ar')->diffForHumans($d));
        $this->assertSame('in 1 second', $d->addSeconds(1)->gregorian()->diffForHumans($d));
    }

    public function testAgoComparesAgainstNowInOwnTimezone(): void
    {
        $this->assertSame('5 minutes ago', CivilDateTime::now('Asia/Tehran')->subMinutes(5)->gregorian()->ago());
        $this->assertSame('in 3 days', CivilDateTime::now('UTC')->addDays(3)->addMinutes(1)->gregorian()->ago());
    }

    // ─── Timestamps ─────────────────────────────────────────────────

    public function testFromTimestampDefaultsToUtc(): void
    {
        $d = CivilDateTime::fromTimestamp(0);
        $this->assertSame('UTC', $d->tzLabel);
        $this->assertSame('1970-01-01 00:00:00', $d->gregorian()->format('Y-m-d H:i:s'));
    }

    public function testFromTimestampInTehran(): void
    {
        // 2026-04-08T10:00:00Z is 13:30 in Tehran (no DST since 2022).
        $d = CivilDateTime::fromTimestamp(1775642400, 'Asia/Tehran');
        $this->assertSame('Asia/Tehran', $d->tzLabel);
        $this->assertSame('1405/01/19 13:30:00', $d->jalali()->format('Y/m/d H:i:s'));
    }

    public function testFromTimestampWithOffsetLabel(): void
    {
        $d = CivilDateTime::fromTimestamp(0, '+03:30');
        $this->assertSame('+03:30', $d->tzLabel);
        $this->assertSame(3 * 3600 + 30 * 60, $d->secondsOfDay);
    }

    public function testFromTimestampNegative(): void
    {
        $d = CivilDateTime::fromTimestamp(-1);
        $this->assertSame('1969-12-31 23:59:59', $d->gregorian()->format('Y-m-d H:i:s'));
    }

    public function testFromTimestampInvalidZoneThrows(): void
    {
        $this->expectException(InvalidTimezoneException::class);
        CivilDateTime::fromTimestamp(0, 'Mars/Olympus');
    }

    public function testToTimestamp(): void
    {
        $d = CivilDateTime::fromJalali(1405, 1, 19, 13, 30, 0, 'Asia/Tehran');
        $this->assertSame(1775642400, $d->toTimestamp());
    }

    public function testToTimestampWithoutTimezoneThrows(): void
    {
        $this->expectException(MissingTimezoneException::class);
        $this->expectExceptionMessage('toTimestamp() requires a timezone');
        CivilDateTime::fromGregorian(2026, 4, 8)->toTimestamp();
    }

    public function testToTimestampInSpringForwardGapMovesForward(): void
    {
        // 2026-03-08 02:30 does not exist in New York; PHP moves it to 03:30 EDT.
        $d = CivilDateTime::fromGregorian(2026, 3, 8, 2, 30, 0, 'America/New_York');
        $this->assertSame('2026-03-08 03:30:00', CivilDateTime::fromTimestamp($d->toTimestamp(), 'America/New_York')
            ->gregorian()->format('Y-m-d H:i:s'));
    }

    public function testAmbiguousFallBackReadingResolvesToEarlierMoment(): void
    {
        // 01:30 happens twice in New York on 2026-11-01 (EDT, then EST).
        $d = CivilDateTime::fromGregorian(2026, 11, 1, 1, 30, 0, 'America/New_York');
        $this->assertSame(1793511000, $d->toTimestamp());
        $this->assertSame('EDT', $d->toDateTimeImmutable()->format('T'));
    }

    public function testToDateTimeImmutableNegativeYear(): void
    {
        $d = CivilDateTime::fromGregorian(-44, 3, 15, 10, 0, 0, 'UTC');
        $this->assertSame('-0044-03-15 10:00:00', $d->toDateTimeImmutable()->format('Y-m-d H:i:s'));
    }

    public function testToDateTimeImmutableBeyondYear9999(): void
    {
        // Arithmetic can step past the Gregorian MAX_YEAR; the escape hatch still works.
        $d = CivilDateTime::fromGregorian(9999, 12, 31, 12, 0, 0, 'UTC')->addDays(1);
        $this->assertSame('10000-01-01 12:00:00', $d->toDateTimeImmutable()->format('Y-m-d H:i:s'));
    }

    // ─── Wall-clock time arithmetic ─────────────────────────────────

    public function testAddHoursRollsOverMidnight(): void
    {
        $d = CivilDateTime::fromGregorian(2026, 4, 8, 22, 15, 0, 'UTC');
        $later = $d->addHours(3);
        $this->assertSame('2026-04-09 01:15:00', $later->gregorian()->format('Y-m-d H:i:s'));
        $this->assertSame('UTC', $later->tzLabel);
    }

    public function testSubMinutesRollsBackOverMidnight(): void
    {
        $d = CivilDateTime::fromGregorian(2026, 1, 1, 0, 0, 30);
        $this->assertSame('2025-12-31 23:59:30', $d->subMinutes(1)->gregorian()->format('Y-m-d H:i:s'));
    }

    public function testAddSecondsMultipleDays(): void
    {
        $d = CivilDateTime::fromGregorian(2026, 4, 8, 12, 0, 0);
        $this->assertSame('2026-04-11 12:00:01', $d->addSeconds(3 * 86400 + 1)->gregorian()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-04-05 11:59:59', $d->subSeconds(3 * 86400 + 1)->gregorian()->format('Y-m-d H:i:s'));
    }

    public function testAddHoursIsWallClockAcrossDst(): void
    {
        // Wall-clock: 01:30 + 1h is 02:30 even though New York skips 02:00–03:00 that night.
        $d = CivilDateTime::fromGregorian(2026, 3, 8, 1, 30, 0, 'America/New_York');
        $this->assertSame('02:30', $d->addHours(1)->gregorian()->format('H:i'));
    }

    public function testAddDaysAndWeeks(): void
    {
        $d = CivilDateTime::fromJalali(1405, 1, 19, 8, 0, 0);
        $this->assertSame('1405/01/26 08:00', $d->addWeeks(1)->jalali()->format('Y/m/d H:i'));
        $this->assertSame('1404/12/27 08:00', $d->subWeeks(3)->jalali()->format('Y/m/d H:i'));
        $this->assertSame('1405/01/20 08:00', $d->addDays(1)->jalali()->format('Y/m/d H:i'));
        $this->assertSame($d->jalali()->subDays(5)->jdn, $d->subDays(5)->jdn);
    }

    public function testStartAndEndOfDay(): void
    {
        $d = CivilDateTime::fromGregorian(2026, 4, 8, 14, 30, 45, 'Asia/Tehran');
        $this->assertSame('2026-04-08 00:00:00', $d->startOfDay()->gregorian()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-04-08 23:59:59', $d->endOfDay()->gregorian()->format('Y-m-d H:i:s'));
        $this->assertSame('Asia/Tehran', $d->endOfDay()->tzLabel);
    }

    public function testDiffInSecondsMinutesHours(): void
    {
        $a = CivilDateTime::fromGregorian(2026, 4, 9, 1, 0, 0);
        $b = CivilDateTime::fromGregorian(2026, 4, 8, 22, 30, 30);
        // 22:30:30 → 01:00:00 is 2h 29m 30s.
        $this->assertSame(8970, $a->diffInSeconds($b));
        $this->assertSame(149, $a->diffInMinutes($b));
        $this->assertSame(2, $a->diffInHours($b));
        $this->assertSame(-2, $b->diffInHours($a));
        $this->assertSame(-149, $b->diffInMinutes($a));
    }

    public function testDiffInDaysIgnoresTimeOfDay(): void
    {
        $a = CivilDateTime::fromGregorian(2026, 4, 9, 1, 0, 0);
        $b = CivilDateTime::fromGregorian(2026, 4, 8, 23, 0, 0);
        $this->assertSame(1, $a->diffInDays($b));
        $this->assertSame(2, $a->diffInHours($b));
    }

    // ─── compare / min / max / between / isSameDay ──────────────────

    public function testCompareSortsWithUsort(): void
    {
        $a = CivilDateTime::fromGregorian(2026, 4, 8, 10);
        $b = CivilDateTime::fromGregorian(2026, 4, 8, 9);
        $c = CivilDateTime::fromGregorian(2025, 12, 31, 23);
        $list = [$a, $b, $c];
        usort($list, CivilDateTime::compare(...));
        $this->assertSame([$c, $b, $a], $list);
        $this->assertSame(0, CivilDateTime::compare($a, CivilDateTime::fromGregorian(2026, 4, 8, 10, 0, 0, 'UTC')));
    }

    public function testMinAndMax(): void
    {
        $a = CivilDateTime::fromGregorian(2026, 4, 8);
        $b = CivilDateTime::fromGregorian(2024, 1, 1);
        $c = CivilDateTime::fromGregorian(2030, 6, 1);
        $this->assertSame($b, CivilDateTime::min($a, $b, $c));
        $this->assertSame($c, CivilDateTime::max($a, $b, $c));
        $this->assertSame($a, CivilDateTime::min($a));
        $tie = CivilDateTime::fromGregorian(2026, 4, 8);
        $this->assertSame($a, CivilDateTime::max($a, $tie), 'first wins on a tie');
    }

    public function testBetween(): void
    {
        $lo = CivilDateTime::fromGregorian(2026, 1, 1);
        $hi = CivilDateTime::fromGregorian(2026, 12, 31);
        $mid = CivilDateTime::fromGregorian(2026, 6, 15);

        $this->assertTrue($mid->between($lo, $hi));
        $this->assertTrue($mid->between($hi, $lo), 'bounds in either order');
        $this->assertTrue($lo->between($lo, $hi));
        $this->assertFalse($lo->between($lo, $hi, inclusive: false));
        $this->assertFalse($mid->between($lo, $lo));
        $this->assertFalse(CivilDateTime::fromGregorian(2027, 1, 1)->between($lo, $hi));
    }

    public function testIsSameDay(): void
    {
        $a = CivilDateTime::fromGregorian(2026, 4, 8, 0, 0, 0);
        $this->assertTrue($a->isSameDay(CivilDateTime::fromGregorian(2026, 4, 8, 23, 59, 59)));
        $this->assertFalse($a->isSameDay(CivilDateTime::fromGregorian(2026, 4, 9)));
    }

    // ─── diffInMonths() ─────────────────────────────────────────────

    public function testDiffInMonthsAcrossYears(): void
    {
        $a = CivilDateTime::fromGregorian(2026, 4, 15);
        $b = CivilDateTime::fromGregorian(2023, 11, 15);
        $this->assertSame(29, $a->gregorian()->diffInMonths($b));
        $this->assertSame(-29, $b->gregorian()->diffInMonths($a));
    }

    public function testDiffInMonthsDayNotReached(): void
    {
        $a = CivilDateTime::fromGregorian(2026, 4, 14);
        $b = CivilDateTime::fromGregorian(2026, 3, 15);
        $this->assertSame(0, $a->gregorian()->diffInMonths($b));
        $this->assertSame(0, $b->gregorian()->diffInMonths($a));
    }

    public function testDiffInMonthsAfterClampedAddMonthsIsZero(): void
    {
        // Jan 31 + 1 month clamps to Feb 28, but Feb 28 has not reached
        // day 31, so the diff back counts no whole month. Documented.
        $jan31 = CivilDateTime::fromGregorian(2026, 1, 31);
        $feb28 = $jan31->gregorian()->addMonths(1);
        $this->assertSame(0, $feb28->gregorian()->diffInMonths($jan31));
    }

    public function testDiffInMonthsInvertsAddMonthsForEveryCalendar(): void
    {
        $start = CivilDateTime::fromGregorian(2026, 4, 8);
        foreach (['gregorian', 'jalali', 'hijri', 'hijriCivil'] as $cal) {
            foreach ([-250, -13, -1, 0, 1, 12, 37, 400] as $n) {
                // Day 1..28 of every calendar survives addMonths without clamping.
                $base = $start->{$cal}()->startOfMonth();
                $moved = $base->{$cal}()->addMonths($n);
                $this->assertSame($n, $moved->{$cal}()->diffInMonths($base), "{$cal} {$n}");
            }
        }
    }

    // ─── diffInYears() ──────────────────────────────────────────────

    public function testDiffInYearsSameDateDifferentYear(): void
    {
        $a = CivilDateTime::fromGregorian(2026, 4, 8);
        $b = CivilDateTime::fromGregorian(2025, 4, 8);
        $this->assertSame(1, $a->gregorian()->diffInYears($b));
    }

    public function testDiffInYearsDayNotReached(): void
    {
        $a = CivilDateTime::fromGregorian(2025, 2, 28);
        $b = CivilDateTime::fromGregorian(2024, 2, 29);
        // Feb 28 hasn't reached Feb 29 yet → 0
        $this->assertSame(0, $a->gregorian()->diffInYears($b));
    }

    public function testDiffInYearsLeapDayToMarch1(): void
    {
        $a = CivilDateTime::fromGregorian(2025, 3, 1);
        $b = CivilDateTime::fromGregorian(2024, 2, 29);
        // Month has passed → 1
        $this->assertSame(1, $a->gregorian()->diffInYears($b));
    }

    public function testDiffInYearsNegative(): void
    {
        $a = CivilDateTime::fromGregorian(2024, 4, 8);
        $b = CivilDateTime::fromGregorian(2026, 4, 8);
        $this->assertSame(-2, $a->gregorian()->diffInYears($b));
    }

    public function testDiffInYearsSymmetry(): void
    {
        $a = CivilDateTime::fromGregorian(2026, 4, 8);
        $b = CivilDateTime::fromGregorian(2016, 4, 8);
        $this->assertSame(10, $a->gregorian()->diffInYears($b));
        $this->assertSame(-10, $b->gregorian()->diffInYears($a));
    }

    public function testDiffInYearsSameDate(): void
    {
        $a = CivilDateTime::fromGregorian(2026, 4, 8);
        $this->assertSame(0, $a->gregorian()->diffInYears($a));
    }

    public function testDiffInYearsJalaliLeapEdge(): void
    {
        // 1403 is a Jalali leap year: Esfand 30 → 1404 Esfand 29
        $a = CivilDateTime::fromJalali(1404, 12, 29);
        $b = CivilDateTime::fromJalali(1403, 12, 30);
        // Day not reached (29 < 30) → 0
        $this->assertSame(0, $a->jalali()->diffInYears($b));
    }

    // ─── Timezone format tokens (integration) ───────────────────────

    public function testFormatPTokenWithTimezone(): void
    {
        $i = CivilDateTime::fromGregorian(2026, 4, 8, 14, 30, 0, 'UTC');
        $this->assertSame('+00:00', $i->gregorian()->format('P'));
    }

    public function testFormatOTokenWithTimezone(): void
    {
        $i = CivilDateTime::fromGregorian(2026, 4, 8, 14, 30, 0, 'UTC');
        $this->assertSame('+0000', $i->gregorian()->format('O'));
    }

    public function testFormatCTokenOnJalaliReturnsGregorian(): void
    {
        // ISO 8601 output should be Gregorian regardless of the view
        $i = CivilDateTime::fromGregorian(2026, 4, 8, 14, 30, 45, 'UTC');
        $jalaliC = $i->jalali()->format('c');
        $gregorianC = $i->gregorian()->format('c');
        // Both produce the same ISO 8601 string since c delegates to DTI
        $this->assertSame($gregorianC, $jalaliC);
    }

    public function testFormatRTokenOutputsRfc2822(): void
    {
        $i = CivilDateTime::fromGregorian(2026, 4, 8, 14, 30, 45, 'UTC');
        $r = $i->gregorian()->format('r');
        $dti = $i->toDateTimeImmutable();
        $this->assertSame($dti->format('r'), $r);
    }

    public function testTimezoneTokenThrowsWithoutTzLabel(): void
    {
        $i = CivilDateTime::fromGregorian(2026, 4, 8);
        $this->expectException(MissingTimezoneException::class);
        $i->gregorian()->format('P');
    }

    public function testTimezoneTokensNotTransliteratedInFullPattern(): void
    {
        $i = CivilDateTime::fromJalali(1405, 1, 19, 14, 30, 0, 'Asia/Tehran');
        $view = $i->jalali()->withLocale('fa')->withDigits('persian');
        // P token should not have Persian digits
        $this->assertSame('+03:30', $view->format('P'));
    }

    public function testTimezoneTokenEscapedDoesNotThrowWithoutTz(): void
    {
        $i = CivilDateTime::fromGregorian(2026, 4, 8);
        // Escaped \P, \O, etc. should not trigger DTI construction or throw
        $this->assertSame('P+O', $i->gregorian()->format('\P+\O'));
    }

    // ─── InvalidTimezoneException ───────────────────────────────────

    public function testNowWithInvalidTimezoneThrows(): void
    {
        $this->expectException(InvalidTimezoneException::class);
        CivilDateTime::now('InvalidZone');
    }

    public function testTodayWithInvalidTimezoneThrows(): void
    {
        $this->expectException(InvalidTimezoneException::class);
        CivilDateTime::today('InvalidZone');
    }

    public function testToDateTimeImmutableWithInvalidTzLabelThrows(): void
    {
        $i = CivilDateTime::fromGregorian(2026, 4, 8, 0, 0, 0, 'InvalidZone');
        $this->expectException(InvalidTimezoneException::class);
        $i->toDateTimeImmutable();
    }

    public function testNowWithValidOffsetSucceeds(): void
    {
        $i = CivilDateTime::now('+03:30');
        $this->assertSame('+03:30', $i->tzLabel);
    }

    // ─── WeekDay enum ───────────────────────────────────────────────

    public function testStartOfWeekEnumMatchesInt(): void
    {
        $i = CivilDateTime::fromGregorian(2026, 4, 8);
        $fromEnum = $i->gregorian()->startOfWeek(WeekDay::Saturday);
        $fromInt = $i->gregorian()->startOfWeek(6);
        $this->assertSame($fromInt->jdn, $fromEnum->jdn);
    }

    public function testEndOfWeekEnumMatchesInt(): void
    {
        $i = CivilDateTime::fromGregorian(2026, 4, 8);
        $fromEnum = $i->gregorian()->endOfWeek(WeekDay::Sunday);
        $fromInt = $i->gregorian()->endOfWeek(7);
        $this->assertSame($fromInt->jdn, $fromEnum->jdn);
    }
}
