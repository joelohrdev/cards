<?php

namespace Database\Factories;

use App\Models\Card;
use App\Models\User;
use App\Models\WantListEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WantListEntry>
 */
class WantListEntryFactory extends Factory
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
            'priority' => 2,
        ];
    }
}
