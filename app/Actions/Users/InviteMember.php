<?php

namespace App\Actions\Users;

use App\Enums\UserRole;
use App\Models\Invitation;
use App\Models\User;
use App\Notifications\UserInvitation;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Email someone a link to join. Any earlier open invitation to the same address stops working.
 */
class InviteMember
{
    /**
     * @return string The invitation link, so it can also be shared by hand.
     */
    public function handle(User $admin, string $email, UserRole $role): string
    {
        $email = Str::lower($email);
        $token = Str::random(40);

        Invitation::query()->open()->where('email', $email)->delete();

        $invitation = Invitation::query()->create([
            'email' => $email,
            'role' => $role,
            'token' => Invitation::hashToken($token),
            'invited_by' => $admin->id,
            'expires_at' => now()->addDays(Invitation::DAYS_VALID),
        ]);

        $url = route('invitations.show', $token);

        Notification::route('mail', $email)->notify(new UserInvitation($invitation, $url));

        return $url;
    }
}
