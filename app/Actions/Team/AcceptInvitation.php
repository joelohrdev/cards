<?php

namespace App\Actions\Team;

use App\Models\Invitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Create the invited person's account. The emailed link proves the address, so it starts verified.
 */
class AcceptInvitation
{
    public function handle(Invitation $invitation, string $name, string $password): User
    {
        return DB::transaction(function () use ($invitation, $name, $password): User {
            $user = new User;
            $user->forceFill([
                'name' => $name,
                'email' => $invitation->email,
                'password' => $password,
                'role' => $invitation->role,
                'email_verified_at' => now(),
            ])->save();

            $invitation->update(['accepted_at' => now()]);

            return $user;
        });
    }
}
