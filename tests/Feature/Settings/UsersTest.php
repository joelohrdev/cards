<?php

use App\Enums\UserRole;
use App\Models\Invitation;
use App\Models\User;
use App\Notifications\UserInvitation;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

test('members can’t manage users', function () {
    $member = User::factory()->create();

    $this->actingAs($member)->get(route('users.index'))->assertForbidden();
    $this->actingAs($member)->post(route('users.invitations.store'), ['email' => 'new@example.com', 'role' => 'member'])->assertForbidden();

    expect(Invitation::query()->count())->toBe(0);
});

test('an admin sees the users and open invitations', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->create();
    Invitation::factory()->for($admin, 'inviter')->create(['email' => 'pending@example.com']);
    Invitation::factory()->for($admin, 'inviter')->expired()->create();

    $this->actingAs($admin)
        ->get(route('users.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/users')
            ->has('members', 2)
            ->has('invitations', 1)
            ->where('invitations.0.email', 'pending@example.com'));
});

test('an admin can invite someone by email', function () {
    Notification::fake();
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('users.invitations.store'), ['email' => 'pat@example.com', 'role' => 'admin'])
        ->assertRedirect(route('users.index'))
        ->assertSessionHasNoErrors();

    $invitation = Invitation::query()->sole();

    expect($invitation->email)->toBe('pat@example.com')
        ->and($invitation->role)->toBe(UserRole::Admin)
        ->and($invitation->invited_by)->toBe($admin->id);

    Notification::assertSentTo(
        new AnonymousNotifiable,
        UserInvitation::class,
        fn (UserInvitation $notification, array $channels, AnonymousNotifiable $notifiable) => $notifiable->routes['mail'] === 'pat@example.com'
            && Invitation::findOpen(basename($notification->url))?->is($invitation),
    );
});

test('someone who already has an account can’t be invited', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->create(['email' => 'taken@example.com']);

    $this->actingAs($admin)
        ->post(route('users.invitations.store'), ['email' => 'taken@example.com', 'role' => 'member'])
        ->assertSessionHasErrors(['email' => 'That person already has an account.']);
});

test('an invited person can set up their account from the link, once', function () {
    $invitation = Invitation::factory()->withToken('secret-token')->create(['email' => 'pat@example.com']);

    $this->get(route('invitations.show', 'secret-token'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('auth/accept-invitation')->where('email', 'pat@example.com'));

    $this->post(route('invitations.accept', 'secret-token'), [
        'name' => 'Pat',
        'password' => 'a-long-password-123',
        'password_confirmation' => 'a-long-password-123',
    ])->assertRedirect(route('dashboard'));

    $user = User::query()->where('email', 'pat@example.com')->sole();

    $this->assertAuthenticatedAs($user);
    expect($user->role)->toBe(UserRole::Member)
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($invitation->fresh()->accepted_at)->not->toBeNull();

    auth()->logout();

    $this->get(route('invitations.show', 'secret-token'))
        ->assertInertia(fn (Assert $page) => $page->where('email', null));
    $this->post(route('invitations.accept', 'secret-token'), ['name' => 'Again', 'password' => 'a-long-password-123', 'password_confirmation' => 'a-long-password-123'])
        ->assertNotFound();
});

test('an expired invitation link can’t be used', function () {
    Invitation::factory()->withToken('old-token')->expired()->create();

    $this->post(route('invitations.accept', 'old-token'), [
        'name' => 'Pat',
        'password' => 'a-long-password-123',
        'password_confirmation' => 'a-long-password-123',
    ])->assertNotFound();

    expect(User::query()->count())->toBe(1);
});

test('an admin can change someone’s role and remove them, but not themselves', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create();

    $this->actingAs($admin)
        ->patch(route('users.update', $member), ['role' => 'admin'])
        ->assertSessionHasNoErrors();

    expect($member->fresh()->role)->toBe(UserRole::Admin);

    $this->actingAs($admin)
        ->patch(route('users.update', $admin), ['role' => 'member'])
        ->assertSessionHasErrors(['member' => 'You can’t change your own role.']);
    $this->actingAs($admin)
        ->delete(route('users.destroy', $admin))
        ->assertSessionHasErrors(['member' => 'You can’t remove yourself.']);

    expect($admin->fresh()->role)->toBe(UserRole::Admin);

    $this->actingAs($admin)
        ->delete(route('users.destroy', $member))
        ->assertRedirect(route('users.index'));

    expect($member->fresh())->toBeNull();
});

test('the first account becomes the admin when roles are added', function () {
    $first = User::factory()->create();
    $second = User::factory()->create();
    $migration = require database_path('migrations/2026_10_30_190230_add_roles_and_invitations.php');

    $migration->down();
    $migration->up();

    expect($first->fresh()->role)->toBe(UserRole::Admin)
        ->and($second->fresh()->role)->toBe(UserRole::Member);
});
