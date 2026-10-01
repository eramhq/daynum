<?php

declare(strict_types=1);

namespace Eram\Daynum\Tests\Unit;

use Eram\Daynum\Calendar\Gregorian\GregorianView;
use Eram\Daynum\Exception\InvalidArgumentException;
use Eram\Daynum\Internal\NameNormalizer;
use Eram\Daynum\Locale\EnglishLocale;
use Eram\Daynum\Locale\LocaleRegistry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Name folding used by `parseExact()` for month, weekday and AM/PM names.
 */
final class NameNormalizerTest extends TestCase
{
    /** @return iterable<string, array{string, string}> */
    public static function foldingProvider(): iterable
    {
        yield 'ASCII'                  => ['APRIL', 'april'];
        yield 'Turkish Ş and Ğ'        => ['ŞEVVAL ĞĞ', 'şevval ğğ'];
        yield 'Turkish dotted İ'       => ['İKİNDİ', 'ikindi'];
        yield 'Turkish dotless ı'      => ['kış', 'kiş'];
        yield 'Turkish dotless I'      => ['KIŞ', 'kiş'];
        yield 'Latin-1 Ç Ö Ü'          => ['ÇARŞAMBA ÖÖ ÜÇ', 'çarşamba öö üç'];
        yield 'Latin-1 edges À Þ'      => ['ÀÞ', 'àþ'];
        yield 'multiplication sign ×'  => ['×', '×'];
        yield 'Arabic yeh and kaf'     => ['ذوالحجة ك', 'ذوالحجة ک'];
        yield 'alef maksura'           => ['ى', 'ی'];
        yield 'ZWNJ and ezafe dropped' => ["سه\u{200C}شنبه ژانویهٔ", 'سهشنبه ژانویه'];
    }

    #[DataProvider('foldingProvider')]
    public function testFolding(string $input, string $expected): void
    {
        $this->assertSame($expected, NameNormalizer::normalize($input));
    }

    /**
     * Folding must never merge two names: within one locale and family, no
     * two months (and no two weekdays) may normalize to the same string.
     */
    #[DataProvider('localeProvider')]
    public function testNormalizedNamesStayUnique(string $tag): void
    {
        $locale = LocaleRegistry::get($tag);

        foreach (['gregorian', 'jalali', 'hijri'] as $family) {
            $owner = [];
            try {
                for ($m = 1; $m <= 12; $m++) {
                    foreach ([$locale->monthName($family, $m), $locale->monthNameShort($family, $m)] as $name) {
                        $key = NameNormalizer::normalize($name);
                        $this->assertSame($m, $owner[$key] ??= $m, "{$tag} {$family}: '{$name}' collides");
                    }
                }
            } catch (InvalidArgumentException) {
                $this->assertSame([], $owner, "{$tag} {$family}: names stop part-way");
            }
        }

        $owner = [];
        for ($w = 0; $w <= 6; $w++) {
            foreach ([$locale->weekdayName($w), $locale->weekdayNameShort($w)] as $name) {
                $key = NameNormalizer::normalize($name);
                $this->assertSame($w, $owner[$key] ??= $w, "{$tag} weekdays: '{$name}' collides");
            }
        }

        foreach ([false, true] as $uppercase) {
            $this->assertNotSame(
                NameNormalizer::normalize($locale->meridiem(false, $uppercase)),
                NameNormalizer::normalize($locale->meridiem(true, $uppercase)),
                "{$tag}: AM and PM fold to the same marker",
            );
        }
    }

    /** @return iterable<string, array{string}> */
    public static function localeProvider(): iterable
    {
        foreach (LocaleRegistry::tags() as $tag) {
            yield $tag => [$tag];
        }
    }

    /**
     * A locale's own AM/PM markers parse alongside the built-in fallbacks,
     * in both of its forms (here the lowercase form is not just the
     * uppercase one folded).
     */
    #[DataProvider('meridiemProvider')]
    public function testLocaleMeridiemParses(string $text, int $hour): void
    {
        LocaleRegistry::register('qmd', new class extends EnglishLocale {
            public function meridiem(bool $isPm, bool $uppercase): string
            {
                return $uppercase ? ($isPm ? 'ÖS' : 'ÖÖ') : ($isPm ? 'ö.s.' : 'ö.ö.');
            }
        });

        $g = GregorianView::parseExact("2026-04-08 {$text}", 'Y-m-d g:i A', null, 'qmd')->gregorian();
        $this->assertSame($hour, $g->hour());
    }

    /** @return iterable<string, array{string, int}> */
    public static function meridiemProvider(): iterable
    {
        yield 'locale PM'          => ['3:00 ÖS', 15];
        yield 'locale AM'          => ['3:00 ÖÖ', 3];
        yield 'locale PM folded'   => ['3:00 ös', 15];
        yield 'locale lowercase PM' => ['3:00 ö.s.', 15];
        yield 'locale lowercase AM' => ['3:00 ö.ö.', 3];
        yield 'fallback pm'        => ['3:00 pm', 15];
        yield 'fallback Persian'   => ['3:00 ب.ظ', 15];
        yield 'fallback Arabic AM' => ['3:00 ص', 3];
    }

    public function testMeridiemMustEndAtAWordBoundary(): void
    {
        $this->assertNull(GregorianView::tryParseExact('2026-04-08 3:00 pmx', 'Y-m-d g:i a'));
    }
}
