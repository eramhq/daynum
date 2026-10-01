<?php

declare(strict_types=1);

namespace Eram\Daynum\Tests\Unit;

use Eram\Daynum\CivilDateTime;
use Eram\Daynum\Locale\ArabicLocale;
use Eram\Daynum\Locale\LocaleRegistry;
use Eram\Daynum\Season;
use InvalidArgumentException;
use Eram\Daynum\WeekDay;
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

    public function testOrdinalSuffixIsEmpty(): void
    {
        $locale = new ArabicLocale();
        $this->assertSame('', $locale->ordinalSuffix(1));
        $this->assertSame('', $locale->ordinalSuffix(11));
        $this->assertSame('', $locale->ordinalSuffix(22));
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
            // Other tests may register extra locales; the shipped ones must be listed.
            $this->assertMatchesRegularExpression('/Available: ([a-z-]+, )*ar, ([a-z-]+, )*en, ([a-z-]+, )*fa, fa-af[,.]/', $e->getMessage());
        }
    }

    // ─── Cross-view integration ───────────────────────────────────────

    public function testArabicHijriFormatRamadan1445(): void
    {
        $d = CivilDateTime::fromGregorian(2024, 3, 11);
        $this->assertSame(
            '1 رمضان 1445',
            $d->hijri()->withLocale('ar')->format('j F Y'),
        );
    }

    /** Full combo: weekday + day + month + year in Arabic, one call. */
    public function testArabicHijriFullFormatCombo(): void
    {
        $d = CivilDateTime::fromHijri(1445, 9, 1);
        $this->assertSame(
            'الاثنين 1 رمضان 1445',
            $d->hijri()->withLocale('ar')->format('l j F Y'),
        );
    }

    public function testArabicWithArabIndicDigits(): void
    {
        $d = CivilDateTime::fromGregorian(2024, 3, 11);
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
        $d = CivilDateTime::fromGregorian(2024, 3, 11); // Monday, 1403-12-21 Jalali
        $jalaliAr = $d->jalali()->withLocale('ar');
        $this->assertSame('1402-12-21', $jalaliAr->format('Y-m-d'));
        $this->assertSame('الاثنين', $jalaliAr->format('l'));
    }

    public function testArabicJalaliMonthTokenThrows(): void
    {
        $d = CivilDateTime::fromGregorian(2024, 3, 11);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown calendar family: jalali');
        $d->jalali()->withLocale('ar')->format('F');
    }

    public function testWeekStartAndWeekend(): void
    {
        $locale = new ArabicLocale();
        $this->assertSame(WeekDay::Sunday, $locale->firstDayOfWeek());
        $this->assertSame([WeekDay::Friday, WeekDay::Saturday], $locale->weekendDays());
    }

    // ─── Relative time ────────────────────────────────────────────────

    /**
     * CLDR Arabic cardinal plural rules: zero = 0; one = 1; two = 2;
     * few = n % 100 in 3..10; many = n % 100 in 11..99; other otherwise
     * (so 100–102 and 200 are "other", while 103 and 111 follow their
     * last two digits). The noun form after the number depends on the
     * category; the "zero" and "other" forms coincide.
     *
     * @dataProvider pluralCategoryCases
     */
    public function testRelativeTimeFollowsPluralCategory(int $n, string $category, string $dayPhrase): void
    {
        $locale = new ArabicLocale();
        $this->assertSame('قبل ' . $dayPhrase, $locale->relativeTime($n, 'day', false), "category {$category}");
        $this->assertSame('خلال ' . $dayPhrase, $locale->relativeTime($n, 'day', true), "category {$category}");
    }

    /**
     * @return iterable<string, array{int, string, string}>
     */
    public static function pluralCategoryCases(): iterable
    {
        yield '0 zero'    => [0, 'zero', '0 يوم'];
        yield '1 one'     => [1, 'one', 'يوم واحد'];
        yield '2 two'     => [2, 'two', 'يومين'];
        yield '3 few'     => [3, 'few', '3 أيام'];
        yield '10 few'    => [10, 'few', '10 أيام'];
        yield '11 many'   => [11, 'many', '11 يومًا'];
        yield '99 many'   => [99, 'many', '99 يومًا'];
        yield '100 other' => [100, 'other', '100 يوم'];
        yield '101 other' => [101, 'other', '101 يوم'];
        yield '102 other' => [102, 'other', '102 يوم'];
        yield '103 few'   => [103, 'few', '103 أيام'];
        yield '111 many'  => [111, 'many', '111 يومًا'];
        yield '200 other' => [200, 'other', '200 يوم'];
    }

    /** CLDR spells past "few" seconds with kasra and future with kasratan. */
    public function testFewSecondsDiacriticDependsOnDirection(): void
    {
        $locale = new ArabicLocale();
        $this->assertSame('قبل 5 ثوانِ', $locale->relativeTime(5, 'second', false));
        $this->assertSame('خلال 5 ثوانٍ', $locale->relativeTime(5, 'second', true));
        // Other categories and units are direction-independent.
        $this->assertSame('قبل 11 ثانية', $locale->relativeTime(11, 'second', false));
        $this->assertSame('قبل 5 دقائق', $locale->relativeTime(5, 'minute', false));
    }

    public function testRelativeTimeNow(): void
    {
        $this->assertSame('الآن', (new ArabicLocale())->relativeTimeNow());
    }

    public function testRelativeTimeRejectsUnknownUnit(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Unknown relative-time unit 'fortnight'");
        (new ArabicLocale())->relativeTime(3, 'fortnight', false);
    }

    public function testRelativeTimeRejectsNegativeValue(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Relative-time value must be non-negative; got -3.');
        (new ArabicLocale())->relativeTime(-3, 'day', false);
    }

    public function testSeasonNames(): void
    {
        $locale = new ArabicLocale();
        $this->assertSame('الربيع', $locale->seasonName(Season::Spring));
        $this->assertSame('الصيف', $locale->seasonName(Season::Summer));
        $this->assertSame('الخريف', $locale->seasonName(Season::Autumn));
        $this->assertSame('الشتاء', $locale->seasonName(Season::Winter));
    }
}
