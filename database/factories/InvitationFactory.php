<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Invitation>
 */
class InvitationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'role' => UserRole::Member,
            'token' => Invitation::hashToken(Str::random(40)),
            'invited_by' => User::factory()->admin(),
            'expires_at' => now()->addDays(Invitation::DAYS_VALID),
        ];
    }

    /**
     * An invitation whose link is the given token.
     */
    public function withToken(string $token): static
    {
        return $this->state(['token' => Invitation::hashToken($token)]);
    }

    public function expired(): static
    {
        return $this->state(['expires_at' => now()->subDay()]);
    }
}
