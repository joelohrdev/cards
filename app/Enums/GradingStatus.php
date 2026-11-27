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
            self::Preparing => 'Going to send',
            self::Submitted => 'Sent to grader',
            self::Received => 'Received by grader',
            self::Shipped => 'Shipped back',
            default => ucfirst($this->value),
        };
    }

    /**
     * The status a submission moves to next, or null once only recording results is left.
     */
    public function next(): ?self
    {
        return match ($this) {
            self::Preparing => self::Submitted,
            self::Submitted => self::Received,
            self::Received => self::Grading,
            self::Grading => self::Shipped,
            self::Shipped, self::Completed => null,
        };
    }

    /**
     * The button that moves a submission into this status.
     */
    public function actionLabel(): string
    {
        return match ($this) {
            self::Preparing => 'Move back to going to send',
            self::Submitted => 'Mark as sent',
            self::Received => 'Mark received by grader',
            self::Grading => 'Mark as grading',
            self::Shipped => 'Mark shipped back',
            self::Completed => 'Record results',
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
