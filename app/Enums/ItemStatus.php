<?php

namespace App\Enums;

enum ItemStatus: string
{
    case Owned = 'owned';
    case Listed = 'listed';
    case AtGrading = 'at-grading';
    case Sold = 'sold';
    case Traded = 'traded';
    case Gone = 'gone';

    public function label(): string
    {
        return match ($this) {
            self::AtGrading => 'At grading',
            self::Gone => 'Gifted / lost',
            default => ucfirst($this->value),
        };
    }

    /**
     * Statuses for cards that are still physically in the collection.
     *
     * @return list<self>
     */
    public static function inHand(): array
    {
        return [self::Owned, self::Listed, self::AtGrading];
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
