<?php

declare(strict_types=1);

namespace Daynum\Formatter;

use InvalidArgumentException;

/**
 * Bidirectional digit transliteration between three Unicode script families.
 *
 * * `latn`    — ASCII `0-9`
 * * `persian` — Persian extended `U+06F0..U+06F9` (۰-۹)
 * * `arab`    — Arabic-Indic `U+0660..U+0669` (٠-٩)
 *
 * The Persian-extended and Arabic-Indic blocks are distinct Unicode scripts
 * representing different regional conventions: Persian sites expect
 * `U+06F0..U+06F9`, Arabic sites expect `U+0660..U+0669`. Treating them as
 * interchangeable is a common bug.
 */
final class DigitTransliterator
{
    public const LATN = 'latn';
    public const PERSIAN = 'persian';
    public const ARAB = 'arab';

    /** @var array<string, array<int, string>> */
    private const DIGITS = [
        self::LATN    => ['0','1','2','3','4','5','6','7','8','9'],
        self::PERSIAN => ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'],
        self::ARAB    => ['٠','١','٢','٣','٤','٥','٦','٧','٨','٩'],
    ];

    /** @var array<string, array<string, string>> */
    private static array $toScriptMap = [];

    /**
     * Convert all ASCII digits in $text to the digits of $script.
     *
     * Non-digit characters, including non-ASCII letters, are left unchanged.
     */
    public static function toScript(string $text, string $script): string
    {
        if ($script === self::LATN) {
            return $text;
        }
        if (!isset(self::$toScriptMap[$script])) {
            // Validate inside the populate branch so an unknown script never
            // poisons the cache with a bad key.
            if (!isset(self::DIGITS[$script])) {
                throw new InvalidArgumentException(
                    "Unknown digit script '{$script}'. Expected one of: latn, persian, arab."
                );
            }
            $d = self::DIGITS[$script];
            self::$toScriptMap[$script] = [
                '0' => $d[0], '1' => $d[1], '2' => $d[2], '3' => $d[3],
                '4' => $d[4], '5' => $d[5], '6' => $d[6], '7' => $d[7],
                '8' => $d[8], '9' => $d[9],
            ];
        }
        return strtr($text, self::$toScriptMap[$script]);
    }

    /**
     * Normalize any Persian or Arabic digits in $text back to ASCII.
     */
    public static function toLatin(string $text): string
    {
        $map = [];
        foreach ([self::PERSIAN, self::ARAB] as $script) {
            foreach (self::DIGITS[$script] as $i => $glyph) {
                $map[$glyph] = (string) $i;
            }
        }
        return strtr($text, $map);
    }

    public static function isSupported(string $script): bool
    {
        return isset(self::DIGITS[$script]);
    }
}
