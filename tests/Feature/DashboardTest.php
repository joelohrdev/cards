<?php

use App\Enums\ItemStatus;
use App\Enums\UserRole;
use App\Models\CollectionItem;
use App\Models\StorageLocation;
use App\Models\User;
use App\Models\ValueSnapshot;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

test('the overview totals in-hand cards across the whole collection', function () {
    $user = User::factory()->create();
    CollectionItem::factory()->for($user)->create(['estimated_value' => 5000, 'purchase_price' => 2000]);
    CollectionItem::factory()->for($user)->create(['estimated_value' => null, 'purchase_price' => 1000]);
    CollectionItem::factory()->for($user)->status(ItemStatus::Sold)->create(['estimated_value' => 99999]);
    CollectionItem::factory()->create(['estimated_value' => 4000, 'purchase_price' => 1000]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('totals.count', 3)
            ->where('totals.value', 9000)
            ->where('totals.cost', 4000)
            ->where('totals.unrealized', 6000)
            ->where('totals.unvalued', 1)
            ->has('topItems', 2)
            ->has('history', 1));
});

test('an empty collection shows admins the setup steps, ticking off what exists', function () {
    $admin = User::factory()->admin()->create();
    StorageLocation::factory()->for($admin)->create();

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('setup', [
            ['key' => 'users', 'done' => false],
            ['key' => 'storage', 'done' => true],
            ['key' => 'tags', 'done' => false],
            ['key' => 'sets', 'done' => false],
            ['key' => 'import', 'done' => false],
            ['key' => 'ebay', 'done' => false],
        ]));
});

test('members are not asked to invite users', function () {
    $member = User::factory()->create();

    $this->actingAs($member)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('setup.0.key', 'storage')->has('setup', 5));
});

test('the setup steps go away once the first card is added', function () {
    $user = User::factory()->admin()->create();
    CollectionItem::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('setup', null));
});

test('one value snapshot is captured per day for the whole collection', function () {
    CollectionItem::factory()->create(['estimated_value' => 1234, 'purchase_price' => 100]);
    CollectionItem::factory()->create(['estimated_value' => 766, 'purchase_price' => 50]);

    $this->artisan('cards:snapshot-values')->assertSuccessful();
    $this->artisan('cards:snapshot-values')->assertSuccessful();

    $snapshot = ValueSnapshot::query()->sole();

    expect($snapshot->total_value)->toBe(2000)
        ->and($snapshot->total_cost)->toBe(150)
        ->and($snapshot->item_count)->toBe(2);
});

test('collector accounts can be created from the command line', function () {
    $this->artisan('cards:create-user', ['email' => 'pat@example.com', 'name' => 'Pat', '--password' => 'secret-pass'])
        ->assertSuccessful();

    $user = User::query()->where('email', 'pat@example.com')->sole();

    expect($user->email_verified_at)->not->toBeNull();
    $this->post(route('login.store'), ['email' => 'pat@example.com', 'password' => 'secret-pass'])->assertRedirect(route('dashboard'));
});

test('the first account created from the command line is an admin', function () {
    $this->artisan('cards:create-user', ['email' => 'pat@example.com', 'name' => 'Pat'])->assertSuccessful();

    expect(User::query()->where('email', 'pat@example.com')->sole()->role)->toBe(UserRole::Admin);
});

test('accounts created once an admin exists are members', function () {
    User::factory()->admin()->create();

    $this->artisan('cards:create-user', ['email' => 'sam@example.com', 'name' => 'Sam'])->assertSuccessful();

    expect(User::query()->where('email', 'sam@example.com')->sole()->role)->toBe(UserRole::Member);
});
