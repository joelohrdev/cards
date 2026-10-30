<?php

namespace App\Enums;

enum SalePlatform: string
{
    case Ebay = 'ebay';
    case CardShow = 'card-show';
    case Local = 'local';
    case Online = 'online';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Ebay => 'eBay',
            self::CardShow => 'Card show',
            self::Local => 'Local / in person',
            self::Online => 'Other online',
            self::Other => 'Other',
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
