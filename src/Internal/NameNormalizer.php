<?php

declare(strict_types=1);

namespace Eram\Daynum\Internal;

/**
 * @internal
 *
 * Folds month, weekday and AM/PM names to the form `parseExact()` compares,
 * so input matches regardless of case, keyboard layout or optional marks.
 * Needs no mbstring: the table covers exactly the letters the built-in
 * locales use.
 */
final class NameNormalizer
{
    /**
     * Applied before ASCII lowercasing. Characters that differ between
     * Arabic and Persian keyboards, marks that carry no meaning for
     * matching, and uppercase letters outside ASCII.
     */
    private const MAP = [
        "\u{064A}" => "\u{06CC}",   // ARABIC YEH → FARSI YEH (ي → ی)
        "\u{0643}" => "\u{06A9}",   // ARABIC KAF → KEHEH (ك → ک)
        "\u{0649}" => "\u{06CC}",   // ALEF MAKSURA → FARSI YEH (ى → ی)
        "\u{200C}" => '',            // ZERO WIDTH NON-JOINER
        "\u{0654}" => '',            // HAMZA ABOVE (Persian ezafe: ژانویهٔ)
        // Latin-1 uppercase → lowercase (À..Þ, skipping ×).
        'À' => 'à', 'Á' => 'á', 'Â' => 'â', 'Ã' => 'ã', 'Ä' => 'ä', 'Å' => 'å',
        'Æ' => 'æ', 'Ç' => 'ç', 'È' => 'è', 'É' => 'é', 'Ê' => 'ê', 'Ë' => 'ë',
        'Ì' => 'ì', 'Í' => 'í', 'Î' => 'î', 'Ï' => 'ï', 'Ð' => 'ð', 'Ñ' => 'ñ',
        'Ò' => 'ò', 'Ó' => 'ó', 'Ô' => 'ô', 'Õ' => 'õ', 'Ö' => 'ö', 'Ø' => 'ø',
        'Ù' => 'ù', 'Ú' => 'ú', 'Û' => 'û', 'Ü' => 'ü', 'Ý' => 'ý', 'Þ' => 'þ',
        // Turkish: Ğ and Ş, and every I (İ, I, ı) folds to i so the
        // dotted/dotless distinction never blocks a match.
        'Ğ' => 'ğ', 'Ş' => 'ş', 'İ' => 'i', 'ı' => 'i',
    ];

    public static function normalize(string $name): string
    {
        return strtolower(strtr($name, self::MAP));
    }
}
