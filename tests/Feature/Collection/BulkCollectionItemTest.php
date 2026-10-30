<?php

use App\Enums\ItemStatus;
use App\Models\CollectionItem;
use App\Models\StorageLocation;
use App\Models\User;

test('selected cards can be tagged, moved, re-statused and valued together', function () {
    $user = User::factory()->create();
    $items = CollectionItem::factory()->for($user)->count(2)->create();
    $location = StorageLocation::factory()->for($user)->create();
    $ids = $items->pluck('id')->all();

    $this->actingAs($user)->post(route('collection.bulk'), ['ids' => $ids, 'action' => 'tag', 'tags' => ['Flip', 'Show']])->assertSessionHasNoErrors();
    $this->actingAs($user)->post(route('collection.bulk'), ['ids' => $ids, 'action' => 'untag', 'tags' => ['Show']])->assertSessionHasNoErrors();
    $this->actingAs($user)->post(route('collection.bulk'), ['ids' => $ids, 'action' => 'move', 'storage_location_id' => $location->id])->assertSessionHasNoErrors();
    $this->actingAs($user)->post(route('collection.bulk'), ['ids' => $ids, 'action' => 'status', 'status' => 'listed'])->assertSessionHasNoErrors();
    $this->actingAs($user)->post(route('collection.bulk'), ['ids' => $ids, 'action' => 'value', 'estimated_value' => '2.5'])->assertSessionHasNoErrors();

    foreach ($items as $item) {
        $item->refresh();
        expect($item->tags->pluck('name')->all())->toBe(['Flip'])
            ->and($item->storage_location_id)->toBe($location->id)
            ->and($item->status)->toBe(ItemStatus::Listed)
            ->and($item->estimated_value)->toBe(250);
    }
});

test('bulk actions apply to cards a teammate added', function () {
    $user = User::factory()->create();
    $mine = CollectionItem::factory()->for($user)->create();
    $teammates = CollectionItem::factory()->create();

    $this->actingAs($user)
        ->post(route('collection.bulk'), ['ids' => [$mine->id, $teammates->id], 'action' => 'delete'])
        ->assertSessionHasNoErrors();

    expect($mine->fresh())->toBeNull()
        ->and($teammates->fresh())->toBeNull();
});

test('a bulk move can use storage a teammate created', function () {
    $user = User::factory()->create();
    $item = CollectionItem::factory()->for($user)->create();
    $location = StorageLocation::factory()->create();

    $this->actingAs($user)
        ->post(route('collection.bulk'), ['ids' => [$item->id], 'action' => 'move', 'storage_location_id' => $location->id])
        ->assertSessionHasNoErrors();

    expect($item->fresh()->storage_location_id)->toBe($location->id);
});

test('a bulk move needs a storage location that exists', function () {
    $user = User::factory()->create();
    $item = CollectionItem::factory()->for($user)->create();

    $this->actingAs($user)
        ->post(route('collection.bulk'), ['ids' => [$item->id], 'action' => 'move', 'storage_location_id' => 999999])
        ->assertSessionHasErrors('storage_location_id');
});
