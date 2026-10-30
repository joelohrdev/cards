<?php

namespace App\Enums;

enum Sport: string
{
    case Baseball = 'baseball';
    case Basketball = 'basketball';
    case Football = 'football';
    case Hockey = 'hockey';
    case Soccer = 'soccer';
    case Golf = 'golf';
    case Racing = 'racing';
    case Wrestling = 'wrestling';
    case Boxing = 'boxing';
    case MultiSport = 'multi-sport';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::MultiSport => 'Multi-sport',
            default => ucfirst($this->value),
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
