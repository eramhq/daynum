<?php

declare(strict_types=1);

namespace Daynum\Tests\EdgeCase;

use Daynum\Instant;
use PHPUnit\Framework\TestCase;

/**
 * Hand-verified Ramadan and Eid anchors against Saudi Supreme Court
 * moonsighting announcements (matching ICU's `islamic-umalqura` data).
 *
 * These dates are the most public-facing thing Daynum does — people
 * Googling "when is Ramadan 2024" expect the answer Daynum gives to
 * match the official Saudi announcement, not an arithmetic approximation.
 */
final class HijriRamadanAnchorTest extends TestCase
{
    /** @dataProvider anchors */
    public function testGregorianDateMatchesUaqRamadan(int $gy, int $gm, int $gd, int $hy, int $hm, int $hd): void
    {
        $instant = Instant::fromGregorian($gy, $gm, $gd);
        $view = $instant->hijri();
        $this->assertSame($hy, $view->year());
        $this->assertSame($hm, $view->month());
        $this->assertSame($hd, $view->day());

        // Round-trip through fromHijri.
        $back = Instant::fromHijri($hy, $hm, $hd);
        $this->assertSame($instant->jdn, $back->jdn);
    }

    /** @return iterable<string, array{int,int,int,int,int,int}> */
    public static function anchors(): iterable
    {
        yield 'Ramadan 1 1444 AH = 2023-03-23'    => [2023, 3, 23, 1444, 9, 1];
        yield 'Ramadan 1 1445 AH = 2024-03-11'    => [2024, 3, 11, 1445, 9, 1];
        yield 'Ramadan 1 1446 AH = 2025-03-01'    => [2025, 3,  1, 1446, 9, 1];
        yield 'Eid al-Fitr 1445 AH = 2024-04-10'  => [2024, 4, 10, 1445, 10, 1];
    }

    public function testRamadanAnchorsFormatInEnglishAndPersian(): void
    {
        $d = Instant::fromGregorian(2024, 3, 11);
        $this->assertSame('1 Ramadan 1445', $d->hijri()->withLocale('en')->format('j F Y'));
        $this->assertSame('1 رمضان 1445', $d->hijri()->withLocale('fa')->format('j F Y'));
        $this->assertSame('١ رمضان ١٤٤٥', $d->hijri()->withLocale('fa')->withDigits('arab')->format('j F Y'));
    }
}
