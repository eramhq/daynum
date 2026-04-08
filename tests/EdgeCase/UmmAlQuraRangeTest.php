<?php

declare(strict_types=1);

namespace Daynum\Tests\EdgeCase;

use Daynum\Calendar\Hijri\Table;
use Daynum\Exception\DaynumException;
use Daynum\Exception\UmmAlQuraOutOfRangeException;
use Daynum\Instant;
use PHPUnit\Framework\TestCase;

/**
 * Boundary behaviour of the Umm al-Qura calendar at the edges of the
 * bundled table. The range is whatever {@see Table} says it is — the
 * tests never hardcode a specific AH year so the fixture can be
 * regenerated against a newer ICU without touching the test source.
 */
final class UmmAlQuraRangeTest extends TestCase
{
    public function testMinYearConstructsAndRoundTrips(): void
    {
        $i = Instant::fromHijri(Table::MIN_YEAR, 1, 1);
        $this->assertSame(Table::MIN_YEAR, $i->hijri()->year());
        $this->assertSame(1, $i->hijri()->month());
        $this->assertSame(1, $i->hijri()->day());
    }

    public function testMaxYearLastDayConstructsAndRoundTrips(): void
    {
        $lastDay = Instant::fromHijri(Table::MAX_YEAR, 12, 1)
            ->hijri()->daysInMonth();
        $i = Instant::fromHijri(Table::MAX_YEAR, 12, $lastDay);
        $this->assertSame(Table::MAX_YEAR, $i->hijri()->year());
        $this->assertSame(12, $i->hijri()->month());
        $this->assertSame($lastDay, $i->hijri()->day());
    }

    public function testYearBelowMinThrows(): void
    {
        $this->expectException(UmmAlQuraOutOfRangeException::class);
        Instant::fromHijri(Table::MIN_YEAR - 1, 1, 1);
    }

    public function testYearAboveMaxThrows(): void
    {
        $this->expectException(UmmAlQuraOutOfRangeException::class);
        Instant::fromHijri(Table::MAX_YEAR + 1, 1, 1);
    }

    public function testOutOfRangeMessageIsHelpful(): void
    {
        try {
            Instant::fromHijri(Table::MIN_YEAR - 1, 5, 15);
            $this->fail('expected throw');
        } catch (UmmAlQuraOutOfRangeException $e) {
            $msg = $e->getMessage();
            $this->assertStringContainsString((string) Table::MIN_YEAR, $msg);
            $this->assertStringContainsString((string) Table::MAX_YEAR, $msg);
            $this->assertStringContainsString('fromHijriCivil', $msg);
            $this->assertStringContainsString('05-15', $msg);
        }
    }

    public function testCatchableAsDaynumException(): void
    {
        try {
            Instant::fromHijri(Table::MIN_YEAR - 1, 1, 1);
            $this->fail('expected throw');
        } catch (DaynumException $e) {
            $this->assertInstanceOf(UmmAlQuraOutOfRangeException::class, $e);
        }
    }

    public function testCivilWorksWhereUaqThrows(): void
    {
        // Same (y, m, d) — UAQ throws, civil succeeds.
        $civilInstant = Instant::fromHijriCivil(Table::MIN_YEAR - 1, 1, 1);
        $this->assertSame(
            Table::MIN_YEAR - 1,
            $civilInstant->hijriCivil()->year()
        );

        $this->expectException(UmmAlQuraOutOfRangeException::class);
        Instant::fromHijri(Table::MIN_YEAR - 1, 1, 1);
    }
}
