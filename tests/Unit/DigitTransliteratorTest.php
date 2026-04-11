<?php

declare(strict_types=1);

namespace Eram\Daynum\Tests\Unit;

use Eram\Daynum\Formatter\DigitTransliterator;
use PHPUnit\Framework\TestCase;

final class DigitTransliteratorTest extends TestCase
{
    public function testLatinIsIdentity(): void
    {
        $this->assertSame('1405/01/19', DigitTransliterator::toScript('1405/01/19', DigitTransliterator::LATN));
    }

    public function testPersianExtendedDigits(): void
    {
        $out = DigitTransliterator::toScript('1405/01/19', DigitTransliterator::PERSIAN);
        $this->assertSame('۱۴۰۵/۰۱/۱۹', $out);
    }

    public function testArabicIndicDigits(): void
    {
        $out = DigitTransliterator::toScript('1405/01/19', DigitTransliterator::ARAB);
        $this->assertSame('١٤٠٥/٠١/١٩', $out);
    }

    public function testPersianDigitsDifferFromArabicDigits(): void
    {
        // This guards the most common bug: treating U+06F0-U+06F9 and
        // U+0660-U+0669 as interchangeable.
        $persian = DigitTransliterator::toScript('0123456789', DigitTransliterator::PERSIAN);
        $arab = DigitTransliterator::toScript('0123456789', DigitTransliterator::ARAB);
        $this->assertNotSame($persian, $arab);
    }

    public function testNormalizeToLatinRoundTrip(): void
    {
        $original = '1405/01/19';
        $persian = DigitTransliterator::toScript($original, DigitTransliterator::PERSIAN);
        $arab = DigitTransliterator::toScript($original, DigitTransliterator::ARAB);

        $this->assertSame($original, DigitTransliterator::toLatin($persian));
        $this->assertSame($original, DigitTransliterator::toLatin($arab));
    }

    public function testNonDigitCharactersAreLeftAlone(): void
    {
        $out = DigitTransliterator::toScript('سه‌شنبه ۱۹ Farvardin 2026', DigitTransliterator::PERSIAN);
        $this->assertStringContainsString('سه‌شنبه', $out);
        $this->assertStringContainsString('Farvardin', $out);
        $this->assertStringContainsString('۲۰۲۶', $out);
    }

    public function testUnknownScriptThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        DigitTransliterator::toScript('123', 'klingon');
    }
}
