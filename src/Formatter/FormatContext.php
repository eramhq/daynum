<?php

declare(strict_types=1);

namespace Daynum\Formatter;

use Daynum\Locale\LocaleData;

/**
 * Everything {@see DateTokenFormatter} needs to render a formatted string.
 *
 * This bundles the calendar components the view has already computed, so the
 * formatter itself is I/O-free and calendar-agnostic.
 */
final class FormatContext
{
    public function __construct(
        public readonly LocaleData $locale,
        public readonly string $calendarName,
        public readonly int $year,
        public readonly int $month,
        public readonly int $day,
        public readonly int $hour,
        public readonly int $minute,
        public readonly int $second,
        /** 0..6, Sunday = 0 (PHP convention) */
        public readonly int $dayOfWeek,
        /** 1..7, Monday = 1 (ISO convention) */
        public readonly int $dayOfWeekIso,
        public readonly int $daysInMonth,
        public readonly int $dayOfYear,
        public readonly int $weekOfYear,
        public readonly int $weekBasedYear,
        public readonly bool $isLeapYear,
        public readonly ?string $tzLabel,
        public readonly string $digitScript,
        public readonly ?\DateTimeImmutable $dateTimeImmutable = null,
    ) {
    }
}
