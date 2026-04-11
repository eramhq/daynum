<?php

declare(strict_types=1);

namespace Eram\Daynum\Tests\Conformance;

use Eram\Daynum\Calendar\Hijri\HijriCivilCalendar;
use Eram\Daynum\Tests\Conformance\Support\FixtureReader;
use PHPUnit\Framework\TestCase;

/**
 * Differential-tests {@see HijriCivilCalendar} against the committed ICU
 * `islamic-civil` oracle fixture.
 *
 * Unlike Jalali (which has documented Birashk-vs-ICU divergences), the
 * tabular Hijri civil algorithm is the same algorithm ICU uses — any
 * disagreement is a bug in either Daynum or the ICU version that produced
 * the fixture. There is no allow-list.
 */
final class HijriCivilIcuConformanceTest extends TestCase
{
    private const FIXTURE = __DIR__ . '/../fixtures/hijri-civil.jsonl.gz';

    public function testHijriCivilMatchesIcuByteForByte(): void
    {
        $this->assertFileExists(self::FIXTURE);

        $calendar = HijriCivilCalendar::instance();
        $rows = 0;
        $mismatches = [];

        foreach (FixtureReader::rows(self::FIXTURE) as $row) {
            $rows++;
            $jdn = $row['jdn'];
            [$hy, $hm, $hd] = $row['h'];

            $actual = $calendar->fromJdn($jdn);
            if ($actual !== [$hy, $hm, $hd]) {
                $mismatches[] = sprintf(
                    'jdn=%d: expected Hijri %d-%02d-%02d, got %d-%02d-%02d',
                    $jdn, $hy, $hm, $hd, $actual[0], $actual[1], $actual[2]
                );
                if (count($mismatches) >= 5) {
                    break;
                }
                continue;
            }

            $back = $calendar->toJdn($hy, $hm, $hd);
            if ($back !== $jdn) {
                $mismatches[] = sprintf(
                    'Hijri %d-%02d-%02d → jdn %d, expected %d',
                    $hy, $hm, $hd, $back, $jdn
                );
                if (count($mismatches) >= 5) {
                    break;
                }
            }
        }

        $this->assertGreaterThan(200_000, $rows, 'Hijri civil fixture appears truncated');
        $this->assertEmpty($mismatches, implode("\n", $mismatches));
    }
}
