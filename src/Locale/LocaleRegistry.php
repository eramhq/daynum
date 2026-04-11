<?php

declare(strict_types=1);

namespace Eram\Daynum\Locale;

use Eram\Daynum\Exception\InvalidArgumentException;

/**
 * Resolves BCP 47 language tags to locale implementations.
 *
 * v1 ships `en`, `fa`, and `ar`. Unknown tags throw; there is no silent
 * fallback so typos surface immediately.
 */
final class LocaleRegistry
{
    private static ?EnglishLocale $en = null;
    private static ?PersianLocale $fa = null;
    private static ?ArabicLocale $ar = null;

    public static function get(string $tag): LocaleData
    {
        return match (strtolower($tag)) {
            'en', 'en-us', 'en_us' => self::$en ??= new EnglishLocale(),
            'fa', 'fa-ir', 'fa_ir' => self::$fa ??= new PersianLocale(),
            'ar', 'ar-sa', 'ar_sa' => self::$ar ??= new ArabicLocale(),
            default => throw new InvalidArgumentException(
                "Unknown locale '{$tag}'. Daynum ships 'en', 'fa', and 'ar'."
            ),
        };
    }
}
