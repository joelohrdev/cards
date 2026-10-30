<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Team\AcceptInvitation;
use App\Concerns\PasswordValidationRules;
use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AcceptInvitationController extends Controller
{
    use PasswordValidationRules;

    /**
     * The form an invited person uses to set their name and password.
     */
    public function show(string $token): Response
    {
        $invitation = Invitation::findOpen($token);

        return Inertia::render('auth/accept-invitation', [
            'token' => $token,
            'email' => $invitation?->email,
            'inviter' => $invitation?->inviter?->name,
        ]);
    }

    /**
     * Create the account and sign them in.
     */
    public function store(Request $request, string $token, AcceptInvitation $acceptInvitation): RedirectResponse
    {
        $invitation = Invitation::findOpen($token);

        abort_if($invitation === null, 404);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'password' => $this->passwordRules(),
        ]);

        if (User::query()->where('email', $invitation->email)->exists()) {
            throw ValidationException::withMessages(['name' => 'There’s already an account for this email. Log in instead.']);
        }

        $user = $acceptInvitation->handle($invitation, $validated['name'], $validated['password']);

        Auth::login($user);
        $request->session()->regenerate();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Welcome! You’re all set.']);

        return to_route('dashboard');
    }
}
