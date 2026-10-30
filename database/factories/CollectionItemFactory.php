<?php

namespace Database\Factories;

use App\Enums\Grader;
use App\Enums\ItemStatus;
use App\Enums\RawCondition;
use App\Models\Card;
use App\Models\CollectionItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CollectionItem>
 */
class CollectionItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'card_id' => Card::factory(),
            'is_graded' => false,
            'raw_condition' => RawCondition::NearMint,
            'status' => ItemStatus::Owned,
            'purchase_price' => fake()->numberBetween(100, 20000),
            'purchase_date' => fake()->dateTimeBetween('-3 years'),
            'estimated_value' => fake()->numberBetween(100, 40000),
        ];
    }

    public function graded(Grader $grader = Grader::Psa, string $grade = '10'): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_graded' => true,
            'grader' => $grader,
            'grade' => $grade,
            'cert_number' => (string) fake()->numberBetween(10000000, 99999999),
            'raw_condition' => null,
        ]);
    }

    public function status(ItemStatus $status): static
    {
        return $this->state(fn (array $attributes): array => ['status' => $status]);
    }
}
