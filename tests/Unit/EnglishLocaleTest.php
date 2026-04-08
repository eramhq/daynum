<?php

declare(strict_types=1);

namespace Daynum\Tests\Unit;

use Daynum\Locale\EnglishLocale;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class EnglishLocaleTest extends TestCase
{
    public function testTagIsEn(): void
    {
        $this->assertSame('en', (new EnglishLocale())->tag());
    }

    public function testGregorianMonthNames(): void
    {
        $locale = new EnglishLocale();
        $this->assertSame('January', $locale->monthName('gregorian', 1));
        $this->assertSame('December', $locale->monthName('gregorian', 12));
        $this->assertSame('Jan', $locale->monthNameShort('gregorian', 1));
        $this->assertSame('Dec', $locale->monthNameShort('gregorian', 12));
    }

    public function testJalaliMonthNamesAreTransliterated(): void
    {
        $locale = new EnglishLocale();
        $this->assertSame('Farvardin', $locale->monthName('jalali', 1));
        $this->assertSame('Esfand', $locale->monthName('jalali', 12));
        // Jalali has no traditional abbreviation; short == long.
        $this->assertSame('Farvardin', $locale->monthNameShort('jalali', 1));
    }

    public function testWeekdayNames(): void
    {
        $locale = new EnglishLocale();
        $this->assertSame('Sunday', $locale->weekdayName(0));
        $this->assertSame('Saturday', $locale->weekdayName(6));
        $this->assertSame('Sun', $locale->weekdayNameShort(0));
    }

    public function testMeridiem(): void
    {
        $locale = new EnglishLocale();
        $this->assertSame('am', $locale->meridiem(false, false));
        $this->assertSame('AM', $locale->meridiem(false, true));
        $this->assertSame('pm', $locale->meridiem(true, false));
        $this->assertSame('PM', $locale->meridiem(true, true));
    }

    public function testInvalidMonthThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new EnglishLocale())->monthName('gregorian', 13);
    }

    public function testInvalidCalendarThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new EnglishLocale())->monthName('hebrew', 1);
    }
}
