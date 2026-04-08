<?php

declare(strict_types=1);

namespace Daynum\Tests\Conformance;

use Daynum\Calendar\Hijri\HijriUmmAlQuraCalendar;
use Daynum\Calendar\Hijri\Table;
use Daynum\Tests\Conformance\Support\FixtureReader;
use Daynum\Tests\Conformance\Support\UmmAlQuraRange;
use PHPUnit\Framework\TestCase;

/**
 * Differential-tests {@see HijriUmmAlQuraCalendar} against the committed
 * ICU `islamic-umalqura` oracle fixture.
 *
 * The fixture is pre-filtered to the native UAQ range (see
 * {@see UmmAlQuraRange} — read from the fixture header, not hardcoded).
 * Daynum's bundled `Table.php` is sourced from the same ICU version as
 * the fixture, so there is no allow-list: any mismatch is a regression.
 */
final class HijriUmmAlQuraIcuConformanceTest extends TestCase
{
    private const FIXTURE = __DIR__ . '/../fixtures/hijri-umalqura.jsonl.gz';

    public function testTableRangeMatchesFixtureHeader(): void
    {
        $this->assertSame(Table::MIN_YEAR, UmmAlQuraRange::minYear());
        $this->assertSame(Table::MAX_YEAR, UmmAlQuraRange::maxYear());
    }

    public function testUmmAlQuraMatchesIcuByteForByte(): void
    {
        $this->assertFileExists(self::FIXTURE);

        $calendar = HijriUmmAlQuraCalendar::instance();
        $rows = 0;
        $mismatches = [];

        foreach (FixtureReader::rows(self::FIXTURE) as $row) {
            $rows++;
            $jdn = $row['jdn'];
            [$hy, $hm, $hd] = $row['h'];

            $actual = $calendar->fromJdn($jdn);
            if ($actual !== [$hy, $hm, $hd]) {
                $mismatches[] = sprintf(
                    'jdn=%d: expected UAQ %d-%02d-%02d, got %d-%02d-%02d',
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
                    'UAQ %d-%02d-%02d → jdn %d, expected %d',
                    $hy, $hm, $hd, $back, $jdn
                );
                if (count($mismatches) >= 5) {
                    break;
                }
            }
        }

        $this->assertGreaterThan(50_000, $rows, 'UAQ fixture appears truncated');
        $this->assertEmpty($mismatches, implode("\n", $mismatches));
    }
}
