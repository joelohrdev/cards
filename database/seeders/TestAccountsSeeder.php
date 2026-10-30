<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Local test accounts. Every account's password is "password".
 *
 * - alex@example.test: demo collection
 * - sam@example.test: separate demo collection (for checking that collections stay private)
 * - new@example.test: empty collection (for onboarding and CSV import)
 *
 * Safe to run repeatedly: existing accounts are reused and demo cards are only added once.
 */
class TestAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            ['email' => 'alex@example.test', 'name' => 'Alex Collector', 'demo' => true],
            ['email' => 'sam@example.test', 'name' => 'Sam Collector', 'demo' => true],
            ['email' => 'new@example.test', 'name' => 'New Collector', 'demo' => false],
        ];

        foreach ($accounts as $account) {
            $user = User::query()->firstOrNew(['email' => $account['email']]);
            $user->forceFill([
                'name' => $account['name'],
                'password' => Hash::make('password'),
                'email_verified_at' => $user->email_verified_at ?? now(),
            ])->save();

            if ($account['demo'] && ! $user->collectionItems()->exists()) {
                $this->call(DemoCollectionSeeder::class, parameters: ['user' => $user]);
            }
        }
    }
}
