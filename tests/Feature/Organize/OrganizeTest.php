<?php

use App\Models\Card;
use App\Models\CollectionItem;
use App\Models\StorageLocation;
use App\Models\Tag;
use App\Models\User;
use App\Models\WantListEntry;
use Inertia\Testing\AssertableInertia as Assert;

test('tags can be created, renamed and deleted, with names unique across everyone', function () {
    $user = User::factory()->create();
    Tag::factory()->create(['name' => 'Flip']);

    $this->actingAs($user)->post(route('tags.store'), ['name' => 'PC', 'color' => 'blue'])->assertSessionHasNoErrors();
    $this->actingAs($user)->post(route('tags.store'), ['name' => 'Flip', 'color' => 'blue'])->assertSessionHasErrors('name');

    $tag = $user->tags()->sole();
    $this->actingAs($user)->patch(route('tags.update', $tag), ['name' => 'Personal collection', 'color' => 'red'])->assertSessionHasNoErrors();
    expect($tag->refresh()->name)->toBe('Personal collection');

    $this->actingAs($user)->delete(route('tags.destroy', $tag))->assertSessionHasNoErrors();
    expect($tag->fresh())->toBeNull();
});

test('the tags page lists every tag with counts', function () {
    $user = User::factory()->create();
    $tag = Tag::factory()->for($user)->create(['name' => 'A']);
    $tag->collectionItems()->attach(CollectionItem::factory()->for($user)->create(['estimated_value' => 1000]));
    Tag::factory()->create(['name' => 'B']);

    $this->actingAs($user)->get(route('tags.index'))->assertInertia(fn (Assert $page) => $page
        ->component('organize/tags')
        ->has('tags', 2)
        ->where('tags.0.items_count', 1)
        ->where('tags.0.total_value', 1000));
});

test('a teammate can rename a tag someone else created', function () {
    $tag = Tag::factory()->create();

    $this->actingAs(User::factory()->create())
        ->patch(route('tags.update', $tag), ['name' => 'Renamed', 'color' => 'red'])
        ->assertSessionHasNoErrors();

    expect($tag->refresh()->name)->toBe('Renamed');
});

test('storage locations nest, show their cards, and cannot contain themselves', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('locations.store'), ['name' => 'Closet', 'type' => 'shelf'])->assertSessionHasNoErrors();
    $closet = $user->storageLocations()->sole();
    $this->actingAs($user)->post(route('locations.store'), ['name' => 'Box 4', 'type' => 'box', 'parent_id' => $closet->id])->assertSessionHasNoErrors();
    $box = $user->storageLocations()->where('name', 'Box 4')->sole();
    $item = CollectionItem::factory()->for($user)->create(['storage_location_id' => $box->id]);

    $this->actingAs($user)->get(route('locations.show', $box))->assertInertia(fn (Assert $page) => $page
        ->component('organize/locations/show')
        ->where('location.path', 'Closet / Box 4')
        ->where('items.0.id', $item->id));

    $this->actingAs($user)
        ->patch(route('locations.update', $closet), ['name' => 'Closet', 'type' => 'shelf', 'parent_id' => $box->id])
        ->assertSessionHasErrors(['parent_id' => 'A location can’t be placed inside itself.']);

    $this->actingAs($user)->get(route('locations.index'))->assertInertia(fn (Assert $page) => $page
        ->where('locations.1.path', 'Closet / Box 4')
        ->where('locations.1.items_count', 1));
});

test('deleting a location leaves its cards unplaced', function () {
    $user = User::factory()->create();
    $location = StorageLocation::factory()->for($user)->create();
    $item = CollectionItem::factory()->for($user)->create(['storage_location_id' => $location->id]);

    $this->actingAs($user)->delete(route('locations.destroy', $location))->assertRedirect(route('locations.index'));

    expect($item->refresh()->storage_location_id)->toBeNull();
});

test('a teammate can open a storage location someone else created', function () {
    $location = StorageLocation::factory()->create();

    $this->actingAs(User::factory()->create())->get(route('locations.show', $location))->assertOk();
});

test('cards can be added to, updated on, and removed from the want list', function () {
    $user = User::factory()->create();
    $cards = Card::factory()->count(2)->create();

    $this->actingAs($user)
        ->post(route('want-list.store'), ['card_ids' => $cards->pluck('id')->all(), 'max_price' => '25'])
        ->assertSessionHasNoErrors();

    $entry = $user->wantListEntries()->first();
    expect($user->wantListEntries()->count())->toBe(2)->and($entry->max_price)->toBe(2500);

    $this->actingAs($user)->patch(route('want-list.update', $entry), ['priority' => 3, 'max_price' => '30'])->assertSessionHasNoErrors();
    expect($entry->refresh()->priority)->toBe(3);

    $this->actingAs($user)->get(route('want-list.index'))->assertInertia(fn (Assert $page) => $page->component('organize/want-list')->has('entries', 2));

    $this->actingAs($user)->delete(route('want-list.destroy', $entry))->assertSessionHasNoErrors();
    expect($user->wantListEntries()->count())->toBe(1);
});

test('wanting a card a teammate already wants updates the existing entry', function () {
    $entry = WantListEntry::factory()->create(['parallel_id' => null, 'max_price' => 1000]);

    $this->actingAs(User::factory()->create())
        ->post(route('want-list.store'), ['card_ids' => [$entry->card_id], 'max_price' => '40'])
        ->assertSessionHasNoErrors();

    expect(WantListEntry::query()->sole()->max_price)->toBe(4000);
});

test('a teammate can remove a want list entry someone else added', function () {
    $entry = WantListEntry::factory()->create();

    $this->actingAs(User::factory()->create())->delete(route('want-list.destroy', $entry))->assertSessionHasNoErrors();

    expect($entry->fresh())->toBeNull();
});
