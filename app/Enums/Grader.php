<?php

namespace App\Enums;

enum Grader: string
{
    case Psa = 'PSA';
    case Bgs = 'BGS';
    case Sgc = 'SGC';
    case Cgc = 'CGC';
    case Csg = 'CSG';
    case Hga = 'HGA';
    case Bvg = 'BVG';
    case Other = 'Other';

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $case): array => ['value' => $case->value, 'label' => $case->value],
            self::cases(),
        );
    }
}
