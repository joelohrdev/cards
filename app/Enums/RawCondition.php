<?php

namespace App\Enums;

/**
 * Conventional raw-card condition scale, usable for both modern and vintage cards.
 */
enum RawCondition: string
{
    case GemMint = 'gem-mint';
    case Mint = 'mint';
    case NearMintMint = 'nm-mt';
    case NearMint = 'nm';
    case ExcellentMint = 'ex-mt';
    case Excellent = 'ex';
    case VeryGoodExcellent = 'vg-ex';
    case VeryGood = 'vg';
    case Good = 'good';
    case Fair = 'fair';
    case Poor = 'poor';

    public function label(): string
    {
        return match ($this) {
            self::GemMint => 'Gem Mint',
            self::Mint => 'Mint',
            self::NearMintMint => 'Near Mint-Mint',
            self::NearMint => 'Near Mint',
            self::ExcellentMint => 'Excellent-Mint',
            self::Excellent => 'Excellent',
            self::VeryGoodExcellent => 'Very Good-Excellent',
            self::VeryGood => 'Very Good',
            self::Good => 'Good',
            self::Fair => 'Fair',
            self::Poor => 'Poor',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $case): array => ['value' => $case->value, 'label' => $case->label()],
            self::cases(),
        );
    }
}
