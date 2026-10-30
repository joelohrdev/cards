<?php

namespace App\Enums;

enum GradingStatus: string
{
    case Preparing = 'preparing';
    case Submitted = 'submitted';
    case Received = 'received';
    case Grading = 'grading';
    case Shipped = 'shipped';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Received => 'Received by grader',
            self::Shipped => 'Shipped back',
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
