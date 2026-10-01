<?php

declare(strict_types=1);

namespace Eram\Daynum\Tests\Property;

use Eram\Daynum\CivilDateTime;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

/**
 * Properties of wall-clock time arithmetic and timestamp conversion.
 *
 * Timestamps are checked against `DateTimeImmutable` in zones with and
 * without DST, including Asia/Tehran (DST observed until 2022).
 */
final class TimeArithmeticTest extends TestCase
{
    private const ITERATIONS = 5_000;
    private const SEED = 0x5EC5;
    private const ZONES = ['UTC', 'Asia/Tehran', 'America/New_York'];

    public function testAddSecondsThenSubtractReturnsOriginal(): void
    {
        $rng = $this->seededRng();
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $start = new CivilDateTime($rng(2_000_000, 2_600_000), $rng(0, 86399), 'UTC');
            $n = $rng(-400_000_000, 400_000_000);

            $moved = $start->addSeconds($n);

            $this->assertTrue($moved->addSeconds(-$n)->equals($start), "Iteration {$i}: n={$n}");
            $this->assertSame($n, $moved->diffInSeconds($start), "Iteration {$i}: n={$n}");
        }
    }

    public function testMinutesAndHoursAgreeWithSeconds(): void
    {
        $rng = $this->seededRng();
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $start = new CivilDateTime($rng(2_000_000, 2_600_000), $rng(0, 86399));
            $n = $rng(-2_000_000, 2_000_000);

            $this->assertTrue($start->addMinutes($n)->equals($start->addSeconds($n * 60)));
            $this->assertTrue($start->addHours($n)->equals($start->addSeconds($n * 3600)));
        }
    }

    public function testFromTimestampMatchesDateTimeImmutable(): void
    {
        $rng = $this->seededRng();
        foreach (self::ZONES as $tz) {
            $zone = new DateTimeZone($tz);
            for ($i = 0; $i < self::ITERATIONS; $i++) {
                $t = $rng(-2_000_000_000, 4_000_000_000);
                $native = (new DateTimeImmutable('@' . $t))->setTimezone($zone);

                $view = CivilDateTime::fromTimestamp($t, $tz)->gregorian();

                $this->assertSame(
                    $native->format('Y-m-d H:i:s'),
                    $view->format('Y-m-d H:i:s'),
                    "{$tz} t={$t}",
                );
            }
        }
    }

    /**
     * `fromTimestamp(t)->toTimestamp() === t`, except for readings in a
     * fall-back overlap: the stored wall-clock reading occurs twice, and
     * (like PHP) `toTimestamp()` resolves it to the earlier moment.
     */
    public function testTimestampRoundTrip(): void
    {
        $rng = $this->seededRng();
        foreach (self::ZONES as $tz) {
            $zone = new DateTimeZone($tz);
            for ($i = 0; $i < self::ITERATIONS; $i++) {
                $t = $rng(-2_000_000_000, 4_000_000_000);
                $back = CivilDateTime::fromTimestamp($t, $tz)->toTimestamp();

                if ($back !== $t) {
                    $earlier = (new DateTimeImmutable('@' . $back))->setTimezone($zone);
                    $later = (new DateTimeImmutable('@' . $t))->setTimezone($zone);
                    $this->assertLessThan($t, $back, "{$tz} t={$t}");
                    $this->assertSame(
                        $earlier->format('Y-m-d H:i:s'),
                        $later->format('Y-m-d H:i:s'),
                        "{$tz} t={$t}: round-trip changed the wall-clock reading",
                    );
                    continue;
                }
                $this->assertSame($t, $back);
            }
        }
    }

    /**
     * @return \Closure(int,int):int
     */
    private function seededRng(): \Closure
    {
        mt_srand(self::SEED);
        return static fn(int $min, int $max): int => mt_rand($min, $max);
    }
}
