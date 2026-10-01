<?php

declare(strict_types=1);

namespace Eram\Daynum\Tests\Unit;

use Eram\Daynum\Exception\InvalidArgumentException;
use Eram\Daynum\Locale\DariLocale;
use Eram\Daynum\Season;
use PHPUnit\Framework\TestCase;

final class DariLocaleTest extends TestCase
{
    /** Only autumn differs from Persian (خزان vs پاییز). */
    public function testSeasonNames(): void
    {
        $locale = new DariLocale();
        $this->assertSame('بهار', $locale->seasonName(Season::Spring));
        $this->assertSame('تابستان', $locale->seasonName(Season::Summer));
        $this->assertSame('خزان', $locale->seasonName(Season::Autumn));
        $this->assertSame('زمستان', $locale->seasonName(Season::Winter));
    }

    public function testRelativeTimeInheritsPersianValidation(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Relative-time value must be non-negative; got -5.');
        (new DariLocale())->relativeTime(-5, 'week', true);
    }
}
