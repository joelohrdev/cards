<?php

use App\Enums\ItemStatus;
use App\Models\CollectionItem;
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
