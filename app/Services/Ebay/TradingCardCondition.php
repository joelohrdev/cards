<?php

namespace App\Services\Ebay;

use App\Enums\Grader;
use App\Enums\RawCondition;
use App\Models\CollectionItem;

/**
 * Maps a collection item onto eBay's trading-card condition model.
 *
 * In the trading card categories (including 261328, Sports Trading Card Singles) a listing must
 * use condition LIKE_NEW (ID 2750) for graded cards or USED_VERY_GOOD (ID 4000) for ungraded
 * cards, together with condition descriptors:
 *
 * - Graded: 27501 Professional Grader (required), 27502 Grade (required),
 *   27503 Certification Number (optional, free text in `additionalInfo`, max 30 chars).
 * - Ungraded: 40001 Card Condition (required).
 *
 * Verified against eBay's developer documentation on 2026-10-29:
 *
 * @see https://developer.ebay.com/api-docs/user-guides/static/mip-user-guide/mip-enum-condition-descriptor-ids-for-trading-cards.html
 * @see https://developer.ebay.com/api-docs/sell/inventory/types/slr:ConditionDescriptor
 */
final class TradingCardCondition
{
    public const string GRADED = 'LIKE_NEW';

    public const string UNGRADED = 'USED_VERY_GOOD';

    public const string DESCRIPTOR_GRADER = '27501';

    public const string DESCRIPTOR_GRADE = '27502';

    public const string DESCRIPTOR_CERTIFICATION = '27503';

    public const string DESCRIPTOR_CARD_CONDITION = '40001';

    /**
     * eBay value IDs for the Professional Grader descriptor (27501).
     *
     * @var array<string, string>
     */
    public const array GRADER_VALUES = [
        'PSA' => '275010',
        'BVG' => '275012',
        'BGS' => '275013',
        'CSG' => '275014',
        'CGC' => '275015',
        'SGC' => '275016',
        'HGA' => '275019',
        'Other' => '2750123',
    ];

    /**
     * eBay value IDs for the Grade descriptor (27502). Whole-number keys become integers in PHP.
     *
     * @var array<int|string, string>
     */
    public const array GRADE_VALUES = [
        '10' => '275020',
        '9.5' => '275021',
        '9' => '275022',
        '8.5' => '275023',
        '8' => '275024',
        '7.5' => '275025',
        '7' => '275026',
        '6.5' => '275027',
        '6' => '275028',
        '5.5' => '275029',
        '5' => '2750210',
        '4.5' => '2750211',
        '4' => '2750212',
        '3.5' => '2750213',
        '3' => '2750214',
        '2.5' => '2750215',
        '2' => '2750216',
        '1.5' => '2750217',
        '1' => '2750218',
        'Authentic' => '2750219',
    ];

    public const string CARD_NEAR_MINT_OR_BETTER = '400010';

    public const string CARD_EXCELLENT = '400011';

    public const string CARD_VERY_GOOD = '400012';

    public const string CARD_POOR = '400013';

    /**
     * The `condition` and `conditionDescriptors` for an inventory item payload.
     *
     * @return array{condition: string, conditionDescriptors: list<array{name: string, values?: list<string>, additionalInfo?: string}>}
     *
     * @throws EbayException when the item's grade or condition can't be expressed in eBay's terms.
     */
    public static function for(CollectionItem $item): array
    {
        if ($item->is_graded) {
            return self::graded($item);
        }

        if ($item->raw_condition === null) {
            throw new EbayException('Set this card’s condition before listing it. eBay requires a card condition for ungraded cards.');
        }

        return [
            'condition' => self::UNGRADED,
            'conditionDescriptors' => [
                ['name' => self::DESCRIPTOR_CARD_CONDITION, 'values' => [self::cardConditionValue($item->raw_condition)]],
            ],
        ];
    }

    /**
     * The ungraded Card Condition value eBay uses for one of our raw conditions.
     */
    public static function cardConditionValue(RawCondition $condition): string
    {
        return match ($condition) {
            RawCondition::GemMint, RawCondition::Mint, RawCondition::NearMintMint, RawCondition::NearMint => self::CARD_NEAR_MINT_OR_BETTER,
            RawCondition::ExcellentMint, RawCondition::Excellent => self::CARD_EXCELLENT,
            RawCondition::VeryGoodExcellent, RawCondition::VeryGood => self::CARD_VERY_GOOD,
            RawCondition::Good, RawCondition::Fair, RawCondition::Poor => self::CARD_POOR,
        };
    }

    /**
     * Normalize a stored grade such as "GEM MT 10", "9.5" or "Auth" to eBay's grade label.
     */
    public static function normalizeGrade(?string $grade): ?string
    {
        $grade = trim((string) $grade);

        if ($grade === '') {
            return null;
        }

        if (preg_match('/^(a|auth|authentic)$/i', $grade) === 1) {
            return 'Authentic';
        }

        if (preg_match('/(\d+(?:\.\d+)?)/', $grade, $matches) !== 1) {
            return null;
        }

        $number = (float) $matches[1];
        $label = rtrim(rtrim(number_format($number, 1, '.', ''), '0'), '.');

        return array_key_exists($label, self::GRADE_VALUES) ? $label : null;
    }

    /**
     * @return array{condition: string, conditionDescriptors: list<array{name: string, values?: list<string>, additionalInfo?: string}>}
     */
    private static function graded(CollectionItem $item): array
    {
        $grade = self::normalizeGrade($item->grade);

        if ($grade === null) {
            throw new EbayException("eBay accepts grades from 1 to 10 in half steps, or Authentic. Edit this card’s grade (currently “{$item->grade}”) and try again.");
        }

        $descriptors = [
            ['name' => self::DESCRIPTOR_GRADER, 'values' => [self::GRADER_VALUES[($item->grader ?? Grader::Other)->value]]],
            ['name' => self::DESCRIPTOR_GRADE, 'values' => [self::GRADE_VALUES[$grade]]],
        ];

        if (filled($item->cert_number)) {
            $descriptors[] = ['name' => self::DESCRIPTOR_CERTIFICATION, 'additionalInfo' => mb_substr(trim($item->cert_number), 0, 30)];
        }

        return ['condition' => self::GRADED, 'conditionDescriptors' => $descriptors];
    }
}
