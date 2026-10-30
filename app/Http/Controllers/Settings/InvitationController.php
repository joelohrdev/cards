<?php

namespace App\Http\Controllers\Settings;

use App\Actions\Team\InviteMember;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Invitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class InvitationController extends Controller
{
    /**
     * Invite someone by email.
     */
    public function store(Request $request, InviteMember $inviteMember): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')],
            'role' => ['required', Rule::enum(UserRole::class)],
        ], [
            'email.unique' => 'That person already has an account.',
        ]);

        $url = $inviteMember->handle($request->user(), $validated['email'], UserRole::from($validated['role']));

        Inertia::flash('invitationUrl', $url);
        Inertia::flash('toast', ['type' => 'success', 'message' => "Invitation sent to {$validated['email']}."]);

        return to_route('team.index');
    }

    /**
     * Cancel an invitation so its link stops working.
     */
    public function destroy(Invitation $invitation): RedirectResponse
    {
        $invitation->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Invitation cancelled.']);

        return to_route('team.index');
    }
}
