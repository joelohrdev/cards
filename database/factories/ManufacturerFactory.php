<?php

namespace Database\Factories;

use App\Models\Manufacturer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Manufacturer>
 */
class ManufacturerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->randomElement(['Topps', 'Panini', 'Upper Deck', 'Bowman', 'Fleer', 'Donruss', 'Leaf', 'Score', 'Skybox', 'Pacific']).' '.fake()->unique()->numberBetween(1, 99999),
        ];
    }
}
