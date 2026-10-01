<?php

declare(strict_types=1);

namespace Eram\Daynum\Formatter;

use Eram\Daynum\Exception\InvalidArgumentException;

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

    /**
     * ASCII digit → script digit, per non-Latin script. Integer keys are
     * fine: `strtr()` compares them as the strings '0'..'9'.
     *
     * @var array<string, array<int, string>>
     */
    private const TO_SCRIPT = [
        self::PERSIAN => ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'],
        self::ARAB    => ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'],
    ];

    /** Persian and Arabic-Indic digit → ASCII digit. */
    private const TO_LATIN = [
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
        '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
        '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
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
        if (!isset(self::TO_SCRIPT[$script])) {
            throw new InvalidArgumentException(
                "Unknown digit script '{$script}'. Expected one of: latn, persian, arab.",
            );
        }
        return strtr($text, self::TO_SCRIPT[$script]);
    }

    /**
     * Normalize any Persian or Arabic digits in $text back to ASCII.
     */
    public static function toLatin(string $text): string
    {
        return strtr($text, self::TO_LATIN);
    }

    public static function isSupported(string $script): bool
    {
        return $script === self::LATN || isset(self::TO_SCRIPT[$script]);
    }
}
