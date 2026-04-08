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
        if (!isset(self::DIGITS[$script])) {
            throw new InvalidArgumentException(
                "Unknown digit script '{$script}'. Expected one of: latn, persian, arab."
            );
        }
        $digits = self::DIGITS[$script];
        return strtr($text, [
            '0' => $digits[0], '1' => $digits[1], '2' => $digits[2], '3' => $digits[3],
            '4' => $digits[4], '5' => $digits[5], '6' => $digits[6], '7' => $digits[7],
            '8' => $digits[8], '9' => $digits[9],
        ]);
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
