<?php

namespace App\Http\Controllers\Settings;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TeamController extends Controller
{
    /**
     * The team, its open invitations, and the form to invite someone.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('settings/team', [
            'members' => User::query()->orderBy('name')->get()->map(fn (User $member): array => [
                'id' => $member->id,
                'name' => $member->name,
                'email' => $member->email,
                'role' => $member->role->value,
                'is_you' => $member->is($request->user()),
                'joined_at' => $member->created_at?->toIso8601String(),
            ]),
            'invitations' => Invitation::query()->open()->latest()->get()->map(fn (Invitation $invitation): array => [
                'id' => $invitation->id,
                'email' => $invitation->email,
                'role' => $invitation->role->value,
                'expires_at' => $invitation->expires_at->toIso8601String(),
            ]),
            'roles' => UserRole::options(),
        ]);
    }

    /**
     * Make a member an admin, or an admin a member.
     */
    public function update(Request $request, User $member): RedirectResponse
    {
        $validated = $request->validate(['role' => ['required', Rule::enum(UserRole::class)]]);

        $this->guardAgainstSelf($request, $member, 'You can’t change your own role.');

        $member->forceFill(['role' => $validated['role']])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$member->name} is now ".($member->isAdmin() ? 'an admin' : 'a member').'.']);

        return to_route('team.index');
    }

    /**
     * Remove someone from the team. The cards and records they added stay.
     */
    public function destroy(Request $request, User $member): RedirectResponse
    {
        $this->guardAgainstSelf($request, $member, 'You can’t remove yourself.');

        $member->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$member->name} was removed."]);

        return to_route('team.index');
    }

    private function guardAgainstSelf(Request $request, User $member, string $message): void
    {
        if ($member->is($request->user())) {
            throw ValidationException::withMessages(['member' => $message]);
        }
    }
}
