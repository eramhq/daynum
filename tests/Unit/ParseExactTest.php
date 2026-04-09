<?php

declare(strict_types=1);

namespace Daynum\Tests\Unit;

use Daynum\Calendar\Gregorian\GregorianView;
use Daynum\Calendar\Hijri\HijriCivilView;
use Daynum\Calendar\Hijri\HijriUmmAlQuraView;
use Daynum\Calendar\Jalali\JalaliView;
use Daynum\Exception\ParseException;
use PHPUnit\Framework\TestCase;

final class ParseExactTest extends TestCase
{
    // ─── Gregorian ───────────────────────────────────────────────────

    public function testGregorianDateOnly(): void
    {
        $i = GregorianView::parseExact('2026-04-08', 'Y-m-d');
        $g = $i->gregorian();

        $this->assertSame(2026, $g->year());
        $this->assertSame(4, $g->month());
        $this->assertSame(8, $g->day());
        $this->assertSame(0, $g->hour());
        $this->assertSame(0, $g->minute());
        $this->assertSame(0, $g->second());
    }

    public function testGregorianWithTime24h(): void
    {
        $i = GregorianView::parseExact('2026-04-08 14:30:45', 'Y-m-d H:i:s');
        $g = $i->gregorian();

        $this->assertSame(14, $g->hour());
        $this->assertSame(30, $g->minute());
        $this->assertSame(45, $g->second());
    }

    public function testGregorianWithTime12hAm(): void
    {
        $i = GregorianView::parseExact('2026-04-08 08:15:00 am', 'Y-m-d h:i:s a');
        $this->assertSame(8, $i->gregorian()->hour());
    }

    public function testGregorianWithTime12hPm(): void
    {
        $i = GregorianView::parseExact('2026-04-08 02:30:00 PM', 'Y-m-d h:i:s A');
        $this->assertSame(14, $i->gregorian()->hour());
    }

    public function testGregorianMidnight12h(): void
    {
        $i = GregorianView::parseExact('2026-04-08 12:00:00 am', 'Y-m-d h:i:s a');
        $this->assertSame(0, $i->gregorian()->hour());
    }

    public function testGregorianNoon12h(): void
    {
        $i = GregorianView::parseExact('2026-04-08 12:00:00 pm', 'Y-m-d h:i:s a');
        $this->assertSame(12, $i->gregorian()->hour());
    }

    public function testGregorianNegativeYear(): void
    {
        $i = GregorianView::parseExact('-0500-06-15', 'Y-m-d');
        $this->assertSame(-500, $i->gregorian()->year());
        $this->assertSame(6, $i->gregorian()->month());
        $this->assertSame(15, $i->gregorian()->day());
    }

    public function testGregorianLeapDay(): void
    {
        $i = GregorianView::parseExact('2024-02-29', 'Y-m-d');
        $this->assertSame(29, $i->gregorian()->day());
    }

    public function testGregorianSlashSeparator(): void
    {
        $i = GregorianView::parseExact('2026/04/08', 'Y/m/d');
        $this->assertSame(2026, $i->gregorian()->year());
    }

    public function testGregorianNoSeparator(): void
    {
        $i = GregorianView::parseExact('20260408', 'Ymd');
        $this->assertSame(2026, $i->gregorian()->year());
        $this->assertSame(4, $i->gregorian()->month());
        $this->assertSame(8, $i->gregorian()->day());
    }

    public function testTimezonePassthrough(): void
    {
        $i = GregorianView::parseExact('2026-04-08', 'Y-m-d', 'Asia/Tehran');
        $this->assertSame('Asia/Tehran', $i->tzLabel);
    }

    public function testTimezoneNullByDefault(): void
    {
        $i = GregorianView::parseExact('2026-04-08', 'Y-m-d');
        $this->assertNull($i->tzLabel);
    }

    // ─── Jalali ──────────────────────────────────────────────────────

    public function testJalaliDateOnly(): void
    {
        $i = JalaliView::parseExact('1405/01/19', 'Y/m/d');
        $j = $i->jalali();

        $this->assertSame(1405, $j->year());
        $this->assertSame(1, $j->month());
        $this->assertSame(19, $j->day());
    }

    public function testJalaliWithTime(): void
    {
        $i = JalaliView::parseExact('1405/01/19 14:30:00', 'Y/m/d H:i:s');
        $j = $i->jalali();

        $this->assertSame(14, $j->hour());
        $this->assertSame(30, $j->minute());
    }

    public function testJalaliPersianDigits(): void
    {
        $i = JalaliView::parseExact('۱۴۰۵/۰۱/۱۹', 'Y/m/d');
        $j = $i->jalali();

        $this->assertSame(1405, $j->year());
        $this->assertSame(1, $j->month());
        $this->assertSame(19, $j->day());
    }

    public function testJalaliArabicIndicDigits(): void
    {
        $i = JalaliView::parseExact('١٤٠٥/٠١/١٩', 'Y/m/d');
        $j = $i->jalali();

        $this->assertSame(1405, $j->year());
    }

    public function testJalaliLeapDay(): void
    {
        // 1403 is a Jalali leap year — Esfand 30 is valid
        $i = JalaliView::parseExact('1403/12/30', 'Y/m/d');
        $this->assertSame(30, $i->jalali()->day());
    }

    // ─── Hijri Umm al-Qura ──────────────────────────────────────────

    public function testHijriUmmAlQuraDateOnly(): void
    {
        $i = HijriUmmAlQuraView::parseExact('1447/10/21', 'Y/m/d');
        $h = $i->hijri();

        $this->assertSame(1447, $h->year());
        $this->assertSame(10, $h->month());
        $this->assertSame(21, $h->day());
    }

    // ─── Hijri Civil ─────────────────────────────────────────────────

    public function testHijriCivilDateOnly(): void
    {
        $i = HijriCivilView::parseExact('1447/10/21', 'Y/m/d');
        $h = $i->hijriCivil();

        $this->assertSame(1447, $h->year());
        $this->assertSame(10, $h->month());
        $this->assertSame(21, $h->day());
    }

    // ─── Round-trip: format → parseExact ─────────────────────────────

    public function testGregorianRoundTrip(): void
    {
        $original = \Daynum\Instant::fromGregorian(2026, 4, 8, 14, 30, 45);
        $formatted = $original->gregorian()->format('Y-m-d H:i:s');
        $parsed = GregorianView::parseExact($formatted, 'Y-m-d H:i:s');

        $this->assertTrue($original->equals($parsed));
    }

    public function testJalaliRoundTrip(): void
    {
        $original = \Daynum\Instant::fromJalali(1405, 1, 19, 8, 0, 0);
        $formatted = $original->jalali()->format('Y/m/d H:i:s');
        $parsed = JalaliView::parseExact($formatted, 'Y/m/d H:i:s');

        $this->assertTrue($original->equals($parsed));
    }

    public function testHijriRoundTrip(): void
    {
        $original = \Daynum\Instant::fromHijri(1447, 10, 21);
        $formatted = $original->hijri()->format('Y/m/d');
        $parsed = HijriUmmAlQuraView::parseExact($formatted, 'Y/m/d');

        $this->assertTrue($original->equals($parsed));
    }

    // ─── Error cases ─────────────────────────────────────────────────

    public function testRejectsInvalidGregorianDate(): void
    {
        $this->expectException(ParseException::class);
        GregorianView::parseExact('2026-02-30', 'Y-m-d');
    }

    public function testRejectsInvalidJalaliDate(): void
    {
        $this->expectException(ParseException::class);
        JalaliView::parseExact('1405/13/01', 'Y/m/d');
    }

    public function testRejectsHijriOutOfRange(): void
    {
        $this->expectException(ParseException::class);
        HijriUmmAlQuraView::parseExact('1200/01/01', 'Y/m/d');
    }

    public function testRejectsFormatMismatch(): void
    {
        $this->expectException(ParseException::class);
        GregorianView::parseExact('2026/04/08', 'Y-m-d');
    }

    public function testRejectsTrailingInput(): void
    {
        $this->expectException(ParseException::class);
        GregorianView::parseExact('2026-04-08 extra', 'Y-m-d');
    }

    public function testRejectsUnsupportedToken(): void
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('not supported for parsing');
        GregorianView::parseExact('2026-4-8', 'Y-n-j');
    }

    public function testRejects12hWithoutMeridiem(): void
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('requires a/A meridiem');
        GregorianView::parseExact('2026-04-08 02:30:00', 'Y-m-d h:i:s');
    }

    public function testRejectsMissingYear(): void
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('must include at least Y, m, and d');
        GregorianView::parseExact('04-08', 'm-d');
    }

    public function testRejectsTooShortInput(): void
    {
        $this->expectException(ParseException::class);
        GregorianView::parseExact('2026-04', 'Y-m-d');
    }

    public function testRejectsNonDigitInNumericField(): void
    {
        $this->expectException(ParseException::class);
        GregorianView::parseExact('2026-AB-08', 'Y-m-d');
    }

    // ─── Escaped characters in format ────────────────────────────────

    public function testEscapedTokenTreatedAsLiteral(): void
    {
        // \Y in format should match literal "Y", not the year token
        $i = GregorianView::parseExact('Y2026-04-08', '\YY-m-d');
        $this->assertSame(2026, $i->gregorian()->year());
    }

    // ─── Persian meridiem ────────────────────────────────────────────

    public function testPersianMeridiemPm(): void
    {
        $i = GregorianView::parseExact('2026-04-08 02:30:00 ب.ظ', 'Y-m-d h:i:s a');
        $this->assertSame(14, $i->gregorian()->hour());
    }

    public function testPersianMeridiemAm(): void
    {
        $i = GregorianView::parseExact('2026-04-08 08:30:00 ق.ظ', 'Y-m-d h:i:s a');
        $this->assertSame(8, $i->gregorian()->hour());
    }
}
