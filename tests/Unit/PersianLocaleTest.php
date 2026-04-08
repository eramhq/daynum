<?php

declare(strict_types=1);

namespace Daynum\Tests\Unit;

use Daynum\Locale\PersianLocale;
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
}
