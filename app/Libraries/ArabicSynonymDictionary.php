<?php

namespace App\Libraries;

/**
 * Arabic legal synonym dictionary.
 *
 * Ported from C# Qanony.Infrastructure.Search.ArabicSynonymDictionary.
 * All 59 synonym groups from the original, terms stored in normalized form.
 */
class ArabicSynonymDictionary
{
    /**
     * @var array<string, list<string>> term -> group members
     */
    private static ?array $lookup = null;

    /**
     * All 59 synonym groups organized by legal domain.
     *
     * @var list<list<string>>
     */
    private const SYNONYM_GROUPS = [
        // Courts & Judiciary
        ['محكمه', 'قضاء', 'هيئه قضائيه'],
        ['قاضي', 'قاض'],
        ['محكمه التمييز', 'محكمه النقض', 'تمييز', 'نقض'],
        ['محكمه الاستئناف', 'استئناف'],
        ['محكمه اول درجه', 'محكمه ابتدائيه', 'ابتدائي'],

        // Lawsuit & Case
        ['دعوي', 'قضيه', 'دعوه'],
        ['مدعي', 'طالب', 'رافع الدعوي'],
        ['مدعي عليه', 'مطلوب', 'خصم'],
        ['خصوم', 'اطراف'],
        ['صحيفه الدعوي', 'عريضه', 'صحيفه'],

        // Rulings & Judgments
        ['حكم', 'قرار', 'فصل'],
        ['حكم نهائي', 'حكم بات', 'حكم قطعي'],
        ['حكم ابتدائي', 'حكم اول درجه'],
        ['منطوق', 'منطوق الحكم'],
        ['اسباب الحكم', 'حيثيات', 'تسبيب'],

        // Appeals & Challenges
        ['طعن', 'اعتراض', 'تظلم'],
        ['طعن بالاستئناف', 'استئناف'],
        ['طعن بالنقض', 'طعن بالتمييز'],
        ['التماس اعاده النظر', 'اعاده النظر', 'التماس'],

        // Contracts & Obligations
        ['عقد', 'اتفاقيه', 'اتفاق'],
        ['التزام', 'تعهد', 'واجب'],
        ['تعويض', 'جبر الضرر'],
        ['ضرر', 'اضرار'],
        ['مسؤوليه', 'مسئوليه'],
        ['اخلال', 'مخالفه', 'نكث'],
        ['فسخ', 'انهاء', 'الغاء'],
        ['بطلان', 'ابطال'],

        // Evidence & Proof
        ['دليل', 'اثبات', 'بينه', 'حجه'],
        ['شاهد', 'شهاده'],
        ['مستند', 'وثيقه', 'محرر'],
        ['خبير', 'خبره'],
        ['قرينه', 'اماره'],

        // Parties & Representatives
        ['محامي', 'وكيل', 'مدافع'],
        ['موكل', 'اصيل'],
        ['نيابه', 'نيابه عامه', 'ادعاء عام'],
        ['متهم', 'مدان'],
        ['مجني عليه', 'ضحيه', 'مضرور'],

        // Procedures
        ['اعلان', 'تبليغ', 'اخطار'],
        ['جلسه', 'محاكمه'],
        ['ميعاد', 'مهله', 'اجل'],
        ['سقوط الحق', 'تقادم'],
        ['اختصاص', 'ولايه'],
        ['اجراءات', 'اجراء'],

        // Property & Finance
        ['ملكيه', 'حيازه', 'تملك'],
        ['دين', 'مبلغ', 'مستحق'],
        ['رهن', 'ضمان', 'كفاله'],
        ['بيع', 'شراء'],
        ['ايجار', 'تاجير', 'استئجار'],

        // Criminal Law
        ['جريمه', 'جنايه', 'جنحه'],
        ['عقوبه', 'جزاء'],
        ['حبس', 'سجن'],
        ['غرامه', 'غرامه ماليه'],
        ['براءه', 'تبرئه'],
        ['ادانه', 'حكم بالادانه'],

        // Laws & Regulations
        ['قانون', 'تشريع', 'نظام'],
        ['ماده', 'نص قانوني', 'بند'],
        ['لائحه', 'تنظيم'],
        ['مرسوم', 'مرسوم بقانون'],

        // Legal Principles
        ['مبدا', 'مبدا قانوني', 'قاعده'],
        ['اجتهاد', 'سابقه قضائيه'],
        ['نظام عام', 'مصلحه عامه'],

        // Common Legal Verbs
        ['قضي', 'حكم', 'فصل'],
        ['طالب', 'ادعي', 'رفع'],
        ['دفع', 'رد', 'اعترض'],
        ['ايد', 'صادق', 'وافق'],
        ['نقض', 'الغي', 'ابطل'],
        ['عدل', 'غير', 'بدل'],
    ];

    /**
     * Build the lookup table (lazy, one-time).
     */
    private static function buildLookup(): void
    {
        if (self::$lookup !== null) {
            return;
        }

        self::$lookup = [];
        foreach (self::SYNONYM_GROUPS as $group) {
            foreach ($group as $term) {
                self::$lookup[$term] = $group;
            }
        }
    }

    /**
     * Get synonyms for a normalized term (excluding the term itself).
     *
     * @param string $normalizedTerm Term in normalized form
     * @return list<string> Synonym terms
     */
    public static function getSynonyms(string $normalizedTerm): array
    {
        self::buildLookup();

        if (!isset(self::$lookup[$normalizedTerm])) {
            return [];
        }

        return array_values(array_filter(
            self::$lookup[$normalizedTerm],
            fn(string $t) => $t !== $normalizedTerm
        ));
    }

    /**
     * Check if a term exists in the dictionary.
     */
    public static function containsTerm(string $normalizedTerm): bool
    {
        self::buildLookup();

        return isset(self::$lookup[$normalizedTerm]);
    }

    /**
     * Get all synonym groups.
     *
     * @return list<list<string>>
     */
    public static function getAllGroups(): array
    {
        return self::SYNONYM_GROUPS;
    }
}
