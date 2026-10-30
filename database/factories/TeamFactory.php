<?php

namespace Database\Factories;

use App\Enums\Sport;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Team>
 */
class TeamFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->city().' '.fake()->randomElement(['Hawks', 'Bears', 'Kings', 'Rockets', 'Giants', 'Pirates']),
            'sport' => fake()->randomElement(Sport::cases()),
        ];
    }
}
