<?php

namespace App\Enums;

enum LocationType: string
{
    case Box = 'box';
    case Binder = 'binder';
    case Case = 'case';
    case Shelf = 'shelf';
    case Row = 'row';
    case Page = 'page';
    case Other = 'other';

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $case): array => ['value' => $case->value, 'label' => ucfirst($case->value)],
            self::cases(),
        );
    }
}
