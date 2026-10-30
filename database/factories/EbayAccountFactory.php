<?php

namespace Database\Factories;

use App\Models\EbayAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EbayAccount>
 */
class EbayAccountFactory extends Factory
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
            'ebay_user_id' => fake()->uuid(),
            'username' => fake()->userName(),
            'access_token' => 'access-'.fake()->sha1(),
            'access_token_expires_at' => now()->addHours(2),
            'refresh_token' => 'refresh-'.fake()->sha1(),
            'refresh_token_expires_at' => now()->addMonths(18),
        ];
    }

    public function readyToList(): static
    {
        return $this->state(fn (array $attributes): array => [
            'fulfillment_policy_id' => 'fulfillment-1',
            'payment_policy_id' => 'payment-1',
            'return_policy_id' => 'return-1',
            'merchant_location_key' => 'cards-default',
        ]);
    }
}
