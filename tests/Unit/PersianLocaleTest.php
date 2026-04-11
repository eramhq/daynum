<?php

declare(strict_types=1);

namespace Eram\Daynum\Tests\Unit;

use Eram\Daynum\Locale\PersianLocale;
use PHPUnit\Framework\TestCase;

final class PersianLocaleTest extends TestCase
{
    public function testTagIsFa(): void
    {
        $this->assertSame('fa', (new PersianLocale())->tag());
    }

    public function testJalaliMonthNamesArePersian(): void
    {
        $locale = new PersianLocale();
        $this->assertSame('فروردین', $locale->monthName('jalali', 1));
        $this->assertSame('اردیبهشت', $locale->monthName('jalali', 2));
        $this->assertSame('اسفند', $locale->monthName('jalali', 12));
    }

    public function testGregorianMonthNamesArePersian(): void
    {
        $locale = new PersianLocale();
        // Long form carries ICU's ezafe hamzeh on vowel-ending names.
        $this->assertSame('ژانویهٔ', $locale->monthName('gregorian', 1));
        $this->assertSame('دسامبر', $locale->monthName('gregorian', 12));
        // Short form drops the ezafe.
        $this->assertSame('ژانویه', $locale->monthNameShort('gregorian', 1));
    }

    public function testHijriMonthNamesArePersian(): void
    {
        $locale = new PersianLocale();
        $this->assertSame('محرم', $locale->monthName('hijri', 1));
        $this->assertSame('صفر', $locale->monthName('hijri', 2));
        $this->assertSame('ربیع‌الاول', $locale->monthName('hijri', 3));
        $this->assertSame('ربیع‌الثانی', $locale->monthName('hijri', 4));
        $this->assertSame('جمادی‌الاول', $locale->monthName('hijri', 5));
        $this->assertSame('جمادی‌الثانی', $locale->monthName('hijri', 6));
        $this->assertSame('رجب', $locale->monthName('hijri', 7));
        $this->assertSame('شعبان', $locale->monthName('hijri', 8));
        $this->assertSame('رمضان', $locale->monthName('hijri', 9));
        $this->assertSame('شوال', $locale->monthName('hijri', 10));
        $this->assertSame('ذیقعده', $locale->monthName('hijri', 11));
        $this->assertSame('ذیحجه', $locale->monthName('hijri', 12));
        // Persian Hijri has no abbreviated form — short aliases long.
        $this->assertSame('محرم', $locale->monthNameShort('hijri', 1));
        $this->assertSame('ذیحجه', $locale->monthNameShort('hijri', 12));
    }

    public function testWeekdayNames(): void
    {
        $locale = new PersianLocale();
        $this->assertSame('یکشنبه', $locale->weekdayName(0));
        $this->assertSame('شنبه', $locale->weekdayName(6));
    }

    public function testMeridiem(): void
    {
        $locale = new PersianLocale();
        $this->assertSame('ق.ظ', $locale->meridiem(false, false));
        $this->assertSame('ب.ظ', $locale->meridiem(true, false));
    }

    public function testOrdinalSuffixIsEmpty(): void
    {
        $locale = new PersianLocale();
        $this->assertSame('', $locale->ordinalSuffix(1));
        $this->assertSame('', $locale->ordinalSuffix(11));
        $this->assertSame('', $locale->ordinalSuffix(22));
    }
}
