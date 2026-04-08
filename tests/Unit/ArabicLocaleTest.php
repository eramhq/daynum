<?php

declare(strict_types=1);

namespace Daynum\Tests\Unit;

use Daynum\Instant;
use Daynum\Locale\ArabicLocale;
use Daynum\Locale\LocaleRegistry;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ArabicLocaleTest extends TestCase
{
    public function testTagIsAr(): void
    {
        $this->assertSame('ar', (new ArabicLocale())->tag());
    }

    public function testGregorianMonthNames(): void
    {
        $locale = new ArabicLocale();
        $this->assertSame('يناير', $locale->monthName('gregorian', 1));
        $this->assertSame('مارس', $locale->monthName('gregorian', 3));
        $this->assertSame('ديسمبر', $locale->monthName('gregorian', 12));
        // ICU ar-SA does not abbreviate — MMM equals MMMM.
        $this->assertSame('يناير', $locale->monthNameShort('gregorian', 1));
    }

    public function testHijriMonthNames(): void
    {
        $locale = new ArabicLocale();
        $this->assertSame('محرم', $locale->monthName('hijri', 1));
        $this->assertSame('ربيع الأول', $locale->monthName('hijri', 3));
        $this->assertSame('رمضان', $locale->monthName('hijri', 9));
        $this->assertSame('ذو الحجة', $locale->monthName('hijri', 12));
    }

    public function testJalaliMonthLookupThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown calendar family: jalali');
        (new ArabicLocale())->monthName('jalali', 1);
    }

    public function testWeekdayNames(): void
    {
        $locale = new ArabicLocale();
        $this->assertSame('الأحد', $locale->weekdayName(0));
        $this->assertSame('الجمعة', $locale->weekdayName(5));
        $this->assertSame('السبت', $locale->weekdayName(6));
        // Arabic has no traditional weekday abbreviation.
        $this->assertSame('الأحد', $locale->weekdayNameShort(0));
    }

    public function testMeridiemIsUnicase(): void
    {
        $locale = new ArabicLocale();
        $this->assertSame('ص', $locale->meridiem(false, false));
        $this->assertSame('م', $locale->meridiem(true, false));
        // Arabic script has no case; the uppercase flag is a no-op.
        $this->assertSame('ص', $locale->meridiem(false, true));
    }

    // ─── LocaleRegistry wiring ────────────────────────────────────────

    public function testRegistryReturnsSameSingletonAcrossAliases(): void
    {
        $a = LocaleRegistry::get('ar');
        $b = LocaleRegistry::get('ar-sa');
        $c = LocaleRegistry::get('ar_sa');
        $d = LocaleRegistry::get('AR');
        $this->assertInstanceOf(ArabicLocale::class, $a);
        $this->assertSame($a, $b);
        $this->assertSame($a, $c);
        $this->assertSame($a, $d);
    }

    public function testUnknownLocaleErrorMessageListsAllShippedLocales(): void
    {
        try {
            LocaleRegistry::get('de');
            $this->fail('Expected InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString("'en'", $e->getMessage());
            $this->assertStringContainsString("'fa'", $e->getMessage());
            $this->assertStringContainsString("'ar'", $e->getMessage());
        }
    }

    // ─── Cross-view integration ───────────────────────────────────────

    public function testArabicHijriFormatRamadan1445(): void
    {
        $d = Instant::fromGregorian(2024, 3, 11);
        $this->assertSame(
            '1 رمضان 1445',
            $d->hijri()->withLocale('ar')->format('j F Y'),
        );
    }

    /** Full combo: weekday + day + month + year in Arabic, one call. */
    public function testArabicHijriFullFormatCombo(): void
    {
        $d = Instant::fromHijri(1445, 9, 1);
        $this->assertSame(
            'الاثنين 1 رمضان 1445',
            $d->hijri()->withLocale('ar')->format('l j F Y'),
        );
    }

    public function testArabicWithArabIndicDigits(): void
    {
        $d = Instant::fromGregorian(2024, 3, 11);
        $this->assertSame(
            '١ رمضان ١٤٤٥',
            $d->hijri()->withLocale('ar')->withDigits('arab')->format('j F Y'),
        );
    }

    /**
     * Numeric-only Jalali patterns and weekday tokens work under the Arabic
     * locale because neither looks up a month name. Only `F`/`M` tokens fail
     * — `testArabicJalaliMonthTokenThrows` pins that path.
     */
    public function testArabicJalaliNonMonthTokensWork(): void
    {
        $d = Instant::fromGregorian(2024, 3, 11); // Monday, 1403-12-21 Jalali
        $jalaliAr = $d->jalali()->withLocale('ar');
        $this->assertSame('1402-12-21', $jalaliAr->format('Y-m-d'));
        $this->assertSame('الاثنين', $jalaliAr->format('l'));
    }

    public function testArabicJalaliMonthTokenThrows(): void
    {
        $d = Instant::fromGregorian(2024, 3, 11);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown calendar family: jalali');
        $d->jalali()->withLocale('ar')->format('F');
    }
}
