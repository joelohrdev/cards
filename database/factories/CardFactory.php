<?php

namespace Database\Factories;

use App\Models\Card;
use App\Models\CardSet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Card>
 */
class CardFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'card_set_id' => CardSet::factory(),
            'number' => (string) fake()->unique()->numberBetween(1, 999999),
            'name' => fake()->name(),
            'is_rookie' => false,
            'is_autograph' => false,
            'is_memorabilia' => false,
            'is_short_print' => false,
        ];
    }

    public function rookie(): static
    {
        return $this->state(fn (array $attributes): array => ['is_rookie' => true]);
    }

    public function autograph(): static
    {
        return $this->state(fn (array $attributes): array => ['is_autograph' => true]);
    }
}
