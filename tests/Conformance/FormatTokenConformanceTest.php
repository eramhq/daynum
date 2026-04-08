<?php

declare(strict_types=1);

namespace Daynum\Tests\Conformance;

use Daynum\Instant;
use Daynum\Locale\LocaleRegistry;
use Daynum\Tests\Conformance\Support\FixtureReader;
use Daynum\Tests\Conformance\Support\JalaliIcuDivergence;
use PHPUnit\Framework\TestCase;

/**
 * Differential-tests {@see \Daynum\Formatter\DateTokenFormatter} against
 * ICU-generated golden strings for each supported token, across both
 * Gregorian and Jalali calendars and both English and Persian locales.
 *
 * Only tokens that have a clean ICU pattern equivalent are covered here —
 * numeric day-of-week (`N`, `w`), `L`, `t`, `T`, and `e` are covered by
 * `DateTokenFormatterTest` unit tests instead.
 */
final class FormatTokenConformanceTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function fixtures(): iterable
    {
        $dir = __DIR__ . '/../fixtures';
        yield 'en gregorian'  => [$dir . '/format-tokens-en.jsonl.gz',        'en', 'gregorian'];
        yield 'fa gregorian'  => [$dir . '/format-tokens-fa.jsonl.gz',        'fa', 'gregorian'];
        yield 'en jalali'     => [$dir . '/format-tokens-en-jalali.jsonl.gz', 'en', 'jalali'];
        yield 'fa jalali'     => [$dir . '/format-tokens-fa-jalali.jsonl.gz', 'fa', 'jalali'];
        yield 'en hijri-civil' => [$dir . '/format-tokens-en-hijri.jsonl.gz',  'en', 'hijri-civil'];
        yield 'fa hijri-civil' => [$dir . '/format-tokens-fa-hijri.jsonl.gz',  'fa', 'hijri-civil'];
    }

    /**
     * @dataProvider fixtures
     */
    public function testFormatterMatchesIcu(string $fixture, string $locale, string $calendar): void
    {
        $this->assertFileExists($fixture);

        // Hoist locale lookup out of the per-row loop — locale is constant
        // across the whole fixture.
        LocaleRegistry::get($locale);

        $rows = 0;
        $checked = 0;
        $mismatches = [];

        foreach (FixtureReader::rows($fixture) as $row) {
            $rows++;
            $jdn = $row['jdn'];

            if ($calendar === 'jalali' && JalaliIcuDivergence::contains($jdn)) {
                continue;
            }

            $instant = new Instant($jdn);
            $view = match ($calendar) {
                'gregorian'   => $instant->gregorian()->withLocale($locale),
                'jalali'      => $instant->jalali()->withLocale($locale),
                'hijri-civil' => $instant->hijriCivil()->withLocale($locale),
            };

            foreach ($row['expected'] as $token => $expected) {
                $actual = $view->format($token);
                if ($actual !== $expected) {
                    $mismatches[] = sprintf(
                        'jdn=%d token=%s locale=%s cal=%s: expected %s, got %s',
                        $jdn, $token, $locale, $calendar,
                        json_encode($expected),
                        json_encode($actual),
                    );
                    if (count($mismatches) >= 10) {
                        break 2;
                    }
                }
                $checked++;
            }
        }

        $this->assertGreaterThan(900, $rows);
        $this->assertGreaterThan(0, $checked);
        $this->assertEmpty($mismatches, implode("\n", $mismatches));
    }
}
