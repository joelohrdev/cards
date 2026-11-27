<?php

use App\Models\Card;
use App\Models\CardSet;
use App\Models\CollectionItem;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('a sticker ID is optional, saved in capitals, and numbered across copies', function () {
    $user = User::factory()->create();
    $card = Card::factory()->create();
    CollectionItem::factory()->create(['sticker_code' => 'A0042']);

    $this->actingAs($user)
        ->post(route('collection.store'), ['card_id' => $card->id])
        ->assertSessionHasNoErrors();

    $this->actingAs($user)
        ->post(route('collection.store'), ['card_id' => $card->id, 'quantity' => 3, 'sticker_code' => ' a0041 '])
        ->assertSessionHasNoErrors();

    expect(CollectionItem::query()->where('card_id', $card->id)->orderBy('id')->pluck('sticker_code')->all())
        ->toBe([null, 'A0041', 'A0043', 'A0044']);
});

test('a sticker ID can only be on one card', function () {
    $user = User::factory()->create();
    $taken = CollectionItem::factory()->for($user)->create(['sticker_code' => 'B7']);

    $this->actingAs($user)
        ->post(route('collection.store'), ['card_id' => Card::factory()->create()->id, 'sticker_code' => 'b7'])
        ->assertSessionHasErrors(['sticker_code' => 'Another card already has that sticker ID.']);

    $this->actingAs($user)
        ->post(route('collection.store'), ['card_id' => Card::factory()->create()->id, 'quantity' => 2, 'sticker_code' => 'PC-A'])
        ->assertSessionHasErrors('sticker_code');

    $this->actingAs($user)
        ->put(route('collection.update', $taken), [
            'card_id' => $taken->card_id,
            'status' => 'owned',
            'sticker_code' => 'B7',
        ])
        ->assertSessionHasNoErrors();
});

test('searching a sticker ID lists that card first, even when it looks like a year', function () {
    $user = User::factory()->create();
    $stickered = CollectionItem::factory()->for($user)->create(['sticker_code' => '2023']);
    $from2023 = CollectionItem::factory()->for($user)->create([
        'card_id' => Card::factory()->for(CardSet::factory()->state(['year' => 2023])),
    ]);

    $this->actingAs($user)
        ->get(route('collection.index', ['q' => '2023']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('items.data.0.id', $stickered->id)
            ->where('items.data.0.sticker_code', '2023')
            ->where('items.data.1.id', $from2023->id));

    $this->actingAs($user)
        ->getJson(route('search', ['q' => '2023']))
        ->assertJsonPath('items.0.id', $stickered->id);
});

test('the add page offers the number after the last sticker used', function () {
    CollectionItem::factory()->create(['sticker_code' => 'A0099']);
    CollectionItem::factory()->create(['sticker_code' => 'A0100']);

    $this->actingAs(User::factory()->create())
        ->get(route('collection.create'))
        ->assertInertia(fn (Assert $page) => $page->where('nextStickerCode', 'A0101'));
});
