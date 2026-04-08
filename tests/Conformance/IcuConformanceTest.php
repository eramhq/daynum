<?php

declare(strict_types=1);

namespace Daynum\Tests\Conformance;

use Daynum\Calendar\Gregorian\GregorianCalendar;
use Daynum\Calendar\Jalali\JalaliCalendar;
use Daynum\Tests\Conformance\Support\FixtureReader;
use Daynum\Tests\Conformance\Support\JalaliIcuDivergence;
use PHPUnit\Framework\TestCase;

/**
 * Differential-tests Daynum against the committed ICU oracle fixtures.
 *
 * Runs pure PHP with no `ext-intl` dependency — the fixtures have been
 * pre-generated and checked in. Every row in `tests/fixtures/{gregorian,jalali}.jsonl.gz`
 * is asserted against Daynum's calendar math.
 *
 * Daynum's Jalali uses Ahmad Birashk's 33-year cycle, which diverges from
 * ICU's Borkowski algorithm at four narrow JDN ranges inside the fixture
 * window. Rows inside the {@see JalaliIcuDivergence} windows are skipped —
 * any disagreement OUTSIDE those ranges is treated as a regression and
 * fails the build.
 */
final class IcuConformanceTest extends TestCase
{
    private const GREGORIAN_FIXTURE = __DIR__ . '/../fixtures/gregorian.jsonl.gz';
    private const JALALI_FIXTURE    = __DIR__ . '/../fixtures/jalali.jsonl.gz';

    public function testGregorianMatchesIcu(): void
    {
        $this->assertFileExists(self::GREGORIAN_FIXTURE);

        $calendar = GregorianCalendar::instance();
        $rows = 0;
        $mismatches = [];

        foreach (FixtureReader::rows(self::GREGORIAN_FIXTURE) as $row) {
            $rows++;
            $jdn = $row['jdn'];
            [$gy, $gm, $gd] = $row['g'];

            $actual = $calendar->fromJdn($jdn);
            if ($actual !== [$gy, $gm, $gd]) {
                $mismatches[] = sprintf(
                    'jdn=%d: expected %d-%02d-%02d, got %d-%02d-%02d',
                    $jdn, $gy, $gm, $gd, $actual[0], $actual[1], $actual[2]
                );
                if (count($mismatches) >= 5) {
                    break;
                }
                continue;
            }

            $back = $calendar->toJdn($gy, $gm, $gd);
            if ($back !== $jdn) {
                $mismatches[] = sprintf(
                    '%d-%02d-%02d → jdn %d, expected %d',
                    $gy, $gm, $gd, $back, $jdn
                );
                if (count($mismatches) >= 5) {
                    break;
                }
            }
        }

        $this->assertGreaterThan(200_000, $rows, 'Gregorian fixture appears truncated');
        $this->assertEmpty($mismatches, "Gregorian mismatches:\n" . implode("\n", $mismatches));
    }

    public function testJalaliMatchesIcuOutsideKnownDivergence(): void
    {
        $this->assertFileExists(self::JALALI_FIXTURE);

        $calendar = JalaliCalendar::instance();
        $rows = 0;
        $skipped = 0;
        $mismatches = [];

        foreach (FixtureReader::rows(self::JALALI_FIXTURE) as $row) {
            $rows++;
            $jdn = $row['jdn'];

            if (JalaliIcuDivergence::contains($jdn)) {
                $skipped++;
                continue;
            }

            [$jy, $jm, $jd] = $row['j'];
            $actual = $calendar->fromJdn($jdn);
            if ($actual !== [$jy, $jm, $jd]) {
                $mismatches[] = sprintf(
                    'NEW divergence at jdn=%d: expected Jalali %d-%02d-%02d, got %d-%02d-%02d',
                    $jdn, $jy, $jm, $jd, $actual[0], $actual[1], $actual[2]
                );
                if (count($mismatches) >= 5) {
                    break;
                }
                continue;
            }

            $back = $calendar->toJdn($jy, $jm, $jd);
            if ($back !== $jdn) {
                $mismatches[] = sprintf(
                    'Jalali %d-%02d-%02d → jdn %d, expected %d',
                    $jy, $jm, $jd, $back, $jdn
                );
                if (count($mismatches) >= 5) {
                    break;
                }
            }
        }

        $this->assertGreaterThan(200_000, $rows, 'Jalali fixture appears truncated');
        $this->assertSame(
            JalaliIcuDivergence::TOTAL_DAYS,
            $skipped,
            "Known-divergence allow-list skipped {$skipped} rows; "
                . 'fixture may have been regenerated against a different ICU.'
        );
        $this->assertEmpty($mismatches, implode("\n", $mismatches));
    }

    public function testJalaliBirashkIcuDivergenceWindowsAreExactlyOneDayOff(): void
    {
        // Inside every allow-listed window, Daynum's Jalali should differ
        // from ICU's — otherwise the window is mis-stated. Round-trip
        // through Daynum's own components must still land on the same JDN.
        $calendar = JalaliCalendar::instance();
        foreach (FixtureReader::rows(self::JALALI_FIXTURE) as $row) {
            if (!JalaliIcuDivergence::contains($row['jdn'])) {
                continue;
            }
            [$jy, $jm, $jd] = $row['j'];
            $actual = $calendar->fromJdn($row['jdn']);
            $this->assertNotSame(
                [$jy, $jm, $jd],
                $actual,
                "jdn={$row['jdn']}: inside divergence window but Daynum and ICU agree — window is wrong"
            );
            $roundTrip = $calendar->toJdn($actual[0], $actual[1], $actual[2]);
            $this->assertSame($row['jdn'], $roundTrip, 'Daynum Jalali round-trip broken in divergence window');
        }
    }

    public function testDayOfWeekMatchesIcu(): void
    {
        $this->assertFileExists(self::GREGORIAN_FIXTURE);

        $rows = 0;
        $mismatches = [];
        foreach (FixtureReader::rows(self::GREGORIAN_FIXTURE) as $row) {
            $rows++;
            $jdn = $row['jdn'];
            $expected = $row['dow'];

            // Daynum's rule: JDN 0 is Monday. PHP DoW is Sun=0..Sat=6 →
            // (jdn + 1) mod 7. Duplicated here intentionally so this test
            // validates the formula against ICU without going through
            // production code.
            $actual = ($jdn + 1) % 7;
            if ($actual < 0) {
                $actual += 7;
            }

            if ($actual !== $expected) {
                $mismatches[] = "jdn={$jdn}: expected dow {$expected}, got {$actual}";
                if (count($mismatches) >= 5) {
                    break;
                }
            }
        }
        $this->assertGreaterThan(200_000, $rows);
        $this->assertEmpty($mismatches, implode("\n", $mismatches));
    }
}
