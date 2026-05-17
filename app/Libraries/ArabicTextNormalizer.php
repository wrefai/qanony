<?php

namespace App\Libraries;

/**
 * Arabic text normalizer for legal document processing.
 *
 * Ported from C# Qanony.Infrastructure.Search.ArabicTextNormalizer.
 *
 * Operations:
 * 1. Normalize Alef variants (أ إ آ ٱ) -> ا
 * 2. Normalize Taa Marbuta (ة) -> ه
 * 3. Normalize Alef Maqsura (ى) -> ي
 * 4. Strip tashkeel/diacritics (fathatan-sukun, superscript alef, tatweel)
 * 5. Collapse whitespace and trim
 */
class ArabicTextNormalizer
{
    /**
     * Alef variants that should all become plain Alef (ا U+0627).
     */
    private const ALEF_VARIANTS = [
        "\xD8\xA3", // أ U+0623 Alef with Hamza Above
        "\xD8\xA5", // إ U+0625 Alef with Hamza Below
        "\xD8\xA2", // آ U+0622 Alef with Madda Above
        "\xD9\xB1", // ٱ U+0671 Alef Wasla
    ];
    private const ALEF_PLAIN = "\xD8\xA7"; // ا U+0627

    /**
     * Taa Marbuta -> Haa
     */
    private const TAA_MARBUTA = "\xD8\xA9"; // ة U+0629
    private const HAA         = "\xD9\x87"; // ه U+0647

    /**
     * Alef Maqsura -> Yaa
     */
    private const ALEF_MAQSURA = "\xD9\x89"; // ى U+0649
    private const YAA          = "\xD9\x8A"; // ي U+064A

    /**
     * Regex pattern matching all Arabic diacritics + tatweel.
     * Covers: U+064B-U+0652 (fathatan through sukun),
     *         U+0670 (superscript alef), U+0640 (tatweel/kashida)
     */
    private const TASHKEEL_PATTERN = '/[\x{064B}-\x{0652}\x{0670}\x{0640}]/u';

    /**
     * Normalize a full text string (document body, search query, etc.).
     *
     * @param string|null $text Raw Arabic text
     * @return string Normalized text
     */
    public static function normalize(?string $text): string
    {
        if ($text === null || $text === '') {
            return '';
        }

        // 1. Normalize Alef variants
        $text = str_replace(self::ALEF_VARIANTS, self::ALEF_PLAIN, $text);

        // 2. Normalize Taa Marbuta
        $text = str_replace(self::TAA_MARBUTA, self::HAA, $text);

        // 3. Normalize Alef Maqsura
        $text = str_replace(self::ALEF_MAQSURA, self::YAA, $text);

        // 4. Strip tashkeel/diacritics
        $text = preg_replace(self::TASHKEEL_PATTERN, '', $text);

        // 5. Collapse whitespace
        $text = preg_replace('/\s+/u', ' ', $text);

        return trim($text);
    }

    /**
     * Normalize a single search term. Same logic as normalize().
     *
     * @param string|null $term Raw term
     * @return string Normalized term
     */
    public static function normalizeTerm(?string $term): string
    {
        return self::normalize($term);
    }
}
