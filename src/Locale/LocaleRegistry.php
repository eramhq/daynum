<?php

declare(strict_types=1);

namespace Daynum\Locale;

use InvalidArgumentException;

/**
 * Resolves BCP 47 language tags to locale implementations.
 *
 * v1 ships `en` and `fa`. Unknown tags throw; there is no silent fallback so
 * typos surface immediately.
 */
final class LocaleRegistry
{
    private static ?EnglishLocale $en = null;
    private static ?PersianLocale $fa = null;

    public static function get(string $tag): LocaleData
    {
        return match (strtolower($tag)) {
            'en', 'en-us', 'en_us' => self::$en ??= new EnglishLocale(),
            'fa', 'fa-ir', 'fa_ir' => self::$fa ??= new PersianLocale(),
            default => throw new InvalidArgumentException(
                "Unknown locale '{$tag}'. Daynum v1 ships 'en' and 'fa'."
            ),
        };
    }
}
