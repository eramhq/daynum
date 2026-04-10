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

    // ─── tryParseExact ─────────────────────────────────────────────────

    public function testTryParseExactReturnsInstantForValidInput(): void
    {
        $i = GregorianView::tryParseExact('2026-04-10', 'Y-m-d');
        $this->assertNotNull($i);
        $this->assertSame(2026, $i->gregorian()->year());
    }

    public function testTryParseExactReturnsNullForInvalidFormat(): void
    {
        $this->assertNull(GregorianView::tryParseExact('not-a-date', 'Y-m-d'));
    }

    public function testTryParseExactReturnsNullForInvalidDate(): void
    {
        $this->assertNull(GregorianView::tryParseExact('2026-02-30', 'Y-m-d'));
    }

    public function testTryParseExactReturnsNullForUnsupportedToken(): void
    {
        // `y` is format-only, so this should return null
        $this->assertNull(GregorianView::tryParseExact('26-04-08', 'y-m-d'));
    }

    public function testTryParseExactPassesThroughTimezone(): void
    {
        $i = GregorianView::tryParseExact('2026-04-10', 'Y-m-d', 'UTC');
        $this->assertNotNull($i);
        $this->assertSame('UTC', $i->tzLabel);
    }

    public function testTryParseExactWorksAcrossCalendars(): void
    {
        $i = JalaliView::tryParseExact('1405/01/21', 'Y/m/d');
        $this->assertNotNull($i);
        $this->assertSame(1405, $i->jalali()->year());
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
        // `y` is format-only (2-digit year is ambiguous for parsing)
        GregorianView::parseExact('26-04-08', 'y-m-d');
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
        $this->expectExceptionMessage('must include at least Y');
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

    // ─── Arabic meridiem ─────────────────────────────────────────────

    public function testArabicMeridiemPm(): void
    {
        $i = GregorianView::parseExact('2026-04-08 02:30:00 م', 'Y-m-d h:i:s a');
        $this->assertSame(14, $i->gregorian()->hour());
    }

    public function testArabicMeridiemAm(): void
    {
        $i = GregorianView::parseExact('2026-04-08 08:30:00 ص', 'Y-m-d h:i:s a');
        $this->assertSame(8, $i->gregorian()->hour());
    }

    public function testArabicMeridiemRoundTrip(): void
    {
        $original = \Daynum\Instant::fromGregorian(2026, 4, 8, 14, 30, 0);
        $formatted = $original->gregorian()->withLocale('ar')->format('Y-m-d h:i:s a');
        $parsed = GregorianView::parseExact($formatted, 'Y-m-d h:i:s a');

        $this->assertSame(14, $parsed->gregorian()->hour());
        $this->assertSame(30, $parsed->gregorian()->minute());
    }

    // ─── Variable-width tokens (n, j, G, g) ─────────────────────────

    public function testVariableWidthSingleDigitMonthDay(): void
    {
        $i = GregorianView::parseExact('2026/4/8', 'Y/n/j');
        $g = $i->gregorian();
        $this->assertSame(2026, $g->year());
        $this->assertSame(4, $g->month());
        $this->assertSame(8, $g->day());
    }

    public function testVariableWidthTwoDigitMonth(): void
    {
        $i = GregorianView::parseExact('2026-12-08', 'Y-n-d');
        $g = $i->gregorian();
        $this->assertSame(12, $g->month());
        $this->assertSame(8, $g->day());
    }

    public function testVariableWidthHour24(): void
    {
        $i = GregorianView::parseExact('2026-04-08 9:05:30', 'Y-m-d G:i:s');
        $g = $i->gregorian();
        $this->assertSame(9, $g->hour());
        $this->assertSame(5, $g->minute());
        $this->assertSame(30, $g->second());
    }

    public function testVariableWidthHour12(): void
    {
        $i = GregorianView::parseExact('2026-04-08 2:30 pm', 'Y-m-d g:i a');
        $this->assertSame(14, $i->gregorian()->hour());
    }

    public function testVariableWidthRoundTrip(): void
    {
        $original = \Daynum\Instant::fromGregorian(2026, 4, 8);
        $formatted = $original->gregorian()->format('Y/n/j');
        $this->assertSame('2026/4/8', $formatted);
        $parsed = GregorianView::parseExact($formatted, 'Y/n/j');
        $this->assertTrue($original->equals($parsed));
    }

    public function testVariableWidthAmbiguousThrows(): void
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('variable-width token');
        GregorianView::parseExact('2026048', 'Ynj');
    }

    public function testVariableWidthPersianDigits(): void
    {
        $i = JalaliView::parseExact('۱۴۰۵/۱/۱۹', 'Y/n/j');
        $j = $i->jalali();
        $this->assertSame(1405, $j->year());
        $this->assertSame(1, $j->month());
        $this->assertSame(19, $j->day());
    }

    public function testTryParseExactVariableWidthReturnsInstant(): void
    {
        $i = GregorianView::tryParseExact('2026/4/8', 'Y/n/j');
        $this->assertNotNull($i);
        $this->assertSame(4, $i->gregorian()->month());
    }

    // ─── Timezone offset parsing (P, O, c) ──────────────────────────

    public function testParsePTokenWithOffset(): void
    {
        $i = GregorianView::parseExact('2026-04-08 14:30:45+03:30', 'Y-m-d H:i:sP');
        $this->assertSame('+03:30', $i->tzLabel);
        $this->assertSame(14, $i->gregorian()->hour());
    }

    public function testParseOTokenWithOffset(): void
    {
        $i = GregorianView::parseExact('2026-04-08 14:30:45 +0330', 'Y-m-d H:i:s O');
        $this->assertSame('+03:30', $i->tzLabel);
    }

    public function testParsePTokenWithZ(): void
    {
        $i = GregorianView::parseExact('2026-04-08 14:30:45Z', 'Y-m-d H:i:sP');
        $this->assertSame('+00:00', $i->tzLabel);
    }

    public function testParsePLowercaseTokenWithZ(): void
    {
        $i = GregorianView::parseExact('2026-04-08T14:30:45Z', 'Y-m-d\TH:i:sp');
        $this->assertSame('+00:00', $i->tzLabel);
        $this->assertSame(14, $i->gregorian()->hour());
    }

    public function testParsePLowercaseTokenWithOffset(): void
    {
        $i = GregorianView::parseExact('2026-04-08 14:30:45+03:30', 'Y-m-d H:i:sp');
        $this->assertSame('+03:30', $i->tzLabel);
    }

    public function testParseCFormatRoundTrip(): void
    {
        $i = GregorianView::parseExact('2026-04-08T14:30:45+03:30', 'c');
        $g = $i->gregorian();
        $this->assertSame(2026, $g->year());
        $this->assertSame(4, $g->month());
        $this->assertSame(8, $g->day());
        $this->assertSame(14, $g->hour());
        $this->assertSame(30, $g->minute());
        $this->assertSame(45, $g->second());
        $this->assertSame('+03:30', $i->tzLabel);
    }

    public function testParseCFormatUTC(): void
    {
        $i = GregorianView::parseExact('2026-04-08T00:00:00+00:00', 'c');
        $this->assertSame('+00:00', $i->tzLabel);
    }

    public function testParsedTzOverridesParameter(): void
    {
        // $tzLabel parameter is 'UTC' but text contains +03:30
        $i = GregorianView::parseExact('2026-04-08 14:30:45+03:30', 'Y-m-d H:i:sP', 'UTC');
        $this->assertSame('+03:30', $i->tzLabel);
    }

    public function testParsedTzFallsBackToParameter(): void
    {
        // No tz token in format — $tzLabel parameter used
        $i = GregorianView::parseExact('2026-04-08', 'Y-m-d', 'UTC');
        $this->assertSame('UTC', $i->tzLabel);
    }

    // ─── Timezone offset validation ─────────────────────────────────

    public function testRejectsInvalidOffsetRange(): void
    {
        $this->expectException(ParseException::class);
        GregorianView::parseExact('2026-04-08 14:30:45+99:99', 'Y-m-d H:i:sP');
    }

    public function testRejectsOffsetHoursTooHigh(): void
    {
        $this->expectException(ParseException::class);
        GregorianView::parseExact('2026-04-08 14:30:45+15:00', 'Y-m-d H:i:sP');
    }

    public function testRejectsOffsetMinutesTooHigh(): void
    {
        $this->expectException(ParseException::class);
        GregorianView::parseExact('2026-04-08 14:30:45+00:60', 'Y-m-d H:i:sP');
    }

    public function testAcceptsMaxValidOffset(): void
    {
        $i = GregorianView::parseExact('2026-04-08 14:30:45+14:00', 'Y-m-d H:i:sP');
        $this->assertSame('+14:00', $i->tzLabel);
    }

    public function testAcceptsChathamIslandsOffset(): void
    {
        $i = GregorianView::parseExact('2026-04-08 14:30:45+13:45', 'Y-m-d H:i:sP');
        $this->assertSame('+13:45', $i->tzLabel);
    }

    public function testRejectsInvalidOFormatOffset(): void
    {
        $this->expectException(ParseException::class);
        GregorianView::parseExact('2026-04-08 14:30:45 +9999', 'Y-m-d H:i:s O');
    }
}
