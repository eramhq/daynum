<?php

declare(strict_types=1);

namespace Eram\Daynum\Locale;

use Eram\Daynum\Exception\InvalidArgumentException;

/**
 * Resolves BCP 47 language tags to locale implementations.
 *
 * Ships `en`, `fa`, `fa-AF` (Dari), `ar`, `ps` (Pashto), `ur` (Urdu) and
 * `tr` (Turkish). Add your own, or replace a built-in, with
 * {@see register()} — typically once at application boot.
 *
 * Tags are matched case-insensitively with `_` treated as `-`, and fall
 * back from the most specific subtag to the language: `fa-IR` resolves to
 * `fa`, `en_US` to `en`, while `fa-AF` has its own entry. A tag that
 * matches nothing throws; there is no silent fallback to English, so typos
 * surface immediately.
 */
final class LocaleRegistry
{
    /** @var array<string, class-string<LocaleData>> */
    private const BUILT_IN = [
        'en'    => EnglishLocale::class,
        'fa'    => PersianLocale::class,
        'fa-af' => DariLocale::class,
        'ar'    => ArabicLocale::class,
        'ps'    => PashtoLocale::class,
        'ur'    => UrduLocale::class,
        'tr'    => TurkishLocale::class,
    ];

    /** @var array<string, LocaleData> resolved and registered locales, by normalized tag */
    private static array $locales = [];

    /**
     * Make a locale available to `withLocale($tag)` and `of($dt, $tag)`.
     *
     * Registering a tag that already exists, including a built-in one,
     * replaces it. Registering a language tag (`ps`) also serves its
     * regional variants (`ps-AF`) unless those are registered separately.
     *
     * @throws InvalidArgumentException if the tag is not a well-formed
     *         BCP 47 language tag (e.g. `ps`, `fa-AF`, `zh-Hant-TW`)
     */
    public static function register(string $tag, LocaleData $locale): void
    {
        self::$locales[self::validTag($tag)] = $locale;
    }

    /**
     * @throws InvalidArgumentException on a malformed or unknown tag
     */
    public static function get(string $tag): LocaleData
    {
        $candidate = self::validTag($tag);
        while ($candidate !== '') {
            $found = self::$locales[$candidate] ?? null;
            if ($found === null && isset(self::BUILT_IN[$candidate])) {
                $class = self::BUILT_IN[$candidate];
                $found = self::$locales[$candidate] = new $class();
            }
            if ($found !== null) {
                return $found;
            }
            // Drop the last subtag: zh-hant-tw → zh-hant → zh.
            $dash = strrpos($candidate, '-');
            $candidate = $dash === false ? '' : substr($candidate, 0, $dash);
        }

        throw new InvalidArgumentException(sprintf(
            "Unknown locale '%s'. Available: %s. Add one with LocaleRegistry::register().",
            $tag,
            implode(', ', self::tags()),
        ));
    }

    /**
     * Whether {@see get()} would resolve this tag.
     *
     * @phpstan-impure the answer changes as locales are registered
     */
    public static function has(string $tag): bool
    {
        try {
            self::get($tag);
            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    /**
     * Normalized tags of every built-in and registered locale.
     *
     * @return list<string>
     * @phpstan-impure the list grows as locales are registered
     */
    public static function tags(): array
    {
        $tags = array_unique([...array_keys(self::BUILT_IN), ...array_keys(self::$locales)]);
        sort($tags);
        return $tags;
    }

    private static function validTag(string $tag): string
    {
        $normalized = self::normalize($tag);
        if (preg_match('/^[a-z]{2,3}(-[a-z0-9]{2,8})*$/', $normalized) !== 1) {
            throw new InvalidArgumentException("Invalid locale tag '{$tag}'; expected a BCP 47 tag such as 'ps' or 'fa-AF'.");
        }
        return $normalized;
    }

    /** Lowercase, with `_` turned into `-`: `fa_AF` → `fa-af`. */
    public static function normalize(string $tag): string
    {
        return strtolower(str_replace('_', '-', trim($tag)));
    }
}
