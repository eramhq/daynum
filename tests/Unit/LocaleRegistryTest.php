<?php

declare(strict_types=1);

namespace Eram\Daynum\Tests\Unit;

use Eram\Daynum\CivilDateTime;
use Eram\Daynum\Exception\InvalidArgumentException;
use Eram\Daynum\Locale\DariLocale;
use Eram\Daynum\Locale\EnglishLocale;
use Eram\Daynum\Locale\LocaleRegistry;
use Eram\Daynum\Locale\PersianLocale;
use Eram\Daynum\WeekDay;
use PHPUnit\Framework\TestCase;

/**
 * The registry is process-global, so every test registers under a tag no
 * other test uses, or restores what it replaced.
 */
final class LocaleRegistryTest extends TestCase
{
    public function testNormalize(): void
    {
        $this->assertSame('fa-af', LocaleRegistry::normalize(' fa_AF '));
    }

    public function testRegionFallsBackToLanguage(): void
    {
        $this->assertInstanceOf(PersianLocale::class, LocaleRegistry::get('fa-IR'));
        $this->assertNotInstanceOf(DariLocale::class, LocaleRegistry::get('fa_IR'));
        $this->assertInstanceOf(EnglishLocale::class, LocaleRegistry::get('en-GB'));
        $this->assertInstanceOf(EnglishLocale::class, LocaleRegistry::get('en-Latn-US'));
        $this->assertInstanceOf(DariLocale::class, LocaleRegistry::get('FA-af'));
    }

    public function testRegisterCustomRegionalLocale(): void
    {
        $canadian = new class () extends EnglishLocale {
            public function tag(): string
            {
                return 'en-CA';
            }

            public function firstDayOfWeek(): WeekDay
            {
                return WeekDay::Sunday;
            }
        };
        LocaleRegistry::register('en_CA', $canadian);

        $this->assertSame($canadian, LocaleRegistry::get('en-ca'));
        $this->assertTrue(LocaleRegistry::has('EN-CA'));
        $this->assertContains('en-ca', LocaleRegistry::tags());

        $wed = CivilDateTime::fromGregorian(2026, 4, 8)->gregorian();
        $this->assertSame('2026-04-05', $wed->withLocale('en-CA')->startOfWeek()->gregorian()->format('Y-m-d'));
        $this->assertSame('2026-04-06', $wed->withLocale('en')->startOfWeek()->gregorian()->format('Y-m-d'));
    }

    public function testRegisteredLanguageServesItsRegions(): void
    {
        $klingon = new class () extends EnglishLocale {
            public function tag(): string
            {
                return 'tlh';
            }
        };
        $this->assertFalse(LocaleRegistry::has('tlh-QO'));
        LocaleRegistry::register('tlh', $klingon);
        $this->assertSame($klingon, LocaleRegistry::get('tlh-QO'));
    }

    public function testRegisterReplacesBuiltIn(): void
    {
        $original = LocaleRegistry::get('fa-AF');
        $replacement = new class () extends PersianLocale {
            public function weekendDays(): array
            {
                return [WeekDay::Friday];
            }
        };
        try {
            LocaleRegistry::register('fa-AF', $replacement);
            $this->assertSame($replacement, LocaleRegistry::get('fa-af'));
        } finally {
            LocaleRegistry::register('fa-AF', $original);
        }
        $this->assertSame($original, LocaleRegistry::get('fa-AF'));
    }

    public function testRegisterRejectsMalformedTag(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid locale tag 'english'");
        LocaleRegistry::register('english', new EnglishLocale());
    }

    public function testGetRejectsMalformedTag(): void
    {
        $this->assertFalse(LocaleRegistry::has('en-!!'));
        $this->assertFalse(LocaleRegistry::has('en-'));
        $this->expectException(InvalidArgumentException::class);
        LocaleRegistry::get('en-');
    }

    public function testUnknownLanguageThrows(): void
    {
        $this->assertFalse(LocaleRegistry::has('de-DE'));
        $this->expectException(InvalidArgumentException::class);
        LocaleRegistry::get('de-DE');
    }

    public function testDariLocale(): void
    {
        $dari = LocaleRegistry::get('fa-AF');
        $this->assertSame('fa-AF', $dari->tag());
        $this->assertSame('حمل', $dari->monthName('jalali', 1));
        $this->assertSame('حوت', $dari->monthName('jalali', 12));
        $this->assertSame('جنوری', $dari->monthName('gregorian', 1));
        $this->assertSame('جنو', $dari->monthNameShort('gregorian', 1));
        $this->assertSame('فبروری', $dari->monthNameShort('gregorian', 2));
        // Hijri names, weekdays and relative time are inherited from Persian.
        $this->assertSame('رمضان', $dari->monthName('hijri', 9));
        $this->assertSame('جمعه', $dari->weekdayName(5));
        $this->assertSame('3 روز پیش', $dari->relativeTime(3, 'day', false));
        $this->assertSame(WeekDay::Saturday, $dari->firstDayOfWeek());
        $this->assertSame([WeekDay::Thursday, WeekDay::Friday], $dari->weekendDays());
    }
}
