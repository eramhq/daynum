<?php

declare(strict_types=1);

namespace Daynum\Exception;

use Daynum\Calendar\Hijri\Table;
use OutOfRangeException;

/**
 * Thrown when a Hijri Umm al-Qura date falls outside the bundled table range.
 *
 * The range is **not a Daynum convention** — it is the exact range over
 * which ICU exposes native Umm al-Qura data. Outside it, ICU silently falls
 * back to the tabular `islamic-civil` calendar, which is precisely the kind
 * of quiet divergence Daynum exists to avoid. If you need dates outside this
 * window, use `Instant::fromHijriCivil()` / `Instant->hijriCivil()` instead —
 * the arithmetic civil calendar has no table-bound limit.
 *
 * The specific range depends on the ICU version that {@see Table} was
 * generated from; always read {@see Table::MIN_YEAR} and {@see Table::MAX_YEAR}
 * rather than assuming a particular pair of numbers.
 */
final class UmmAlQuraOutOfRangeException extends OutOfRangeException implements DaynumException
{
    public static function forYear(int $year, int $month, int $day): self
    {
        return new self(sprintf(
            'Hijri Umm al-Qura date %d-%02d-%02d is outside the supported '
            . 'range (AH %d to AH %d). Use fromHijriCivil() for dates '
            . 'outside this range.',
            $year,
            $month,
            $day,
            Table::MIN_YEAR,
            Table::MAX_YEAR,
        ));
    }

    public static function forJdn(int $jdn): self
    {
        return new self(sprintf(
            'JDN %d is outside the supported Hijri Umm al-Qura range '
            . '(AH %d to AH %d). Use hijriCivil() for dates outside this range.',
            $jdn,
            Table::MIN_YEAR,
            Table::MAX_YEAR,
        ));
    }
}
