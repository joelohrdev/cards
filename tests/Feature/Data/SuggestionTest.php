<?php

use App\Models\CollectionItem;
use App\Models\Tag;
use App\Models\User;

test('guests can’t see suggestions', function () {
    $this->getJson(route('suggestions', 'purchase_source'))->assertUnauthorized();
});

test('values already used are suggested, starting-with matches first, then the most used', function () {
    $user = User::factory()->create();
    CollectionItem::factory()->count(3)->for($user)->create(['purchase_source' => 'Card show']);
    CollectionItem::factory()->for($user)->create(['purchase_source' => 'Showcase Cards']);
    CollectionItem::factory()->count(2)->for($user)->create(['purchase_source' => 'eBay']);
    CollectionItem::factory()->for($user)->create(['purchase_source' => null]);

    $this->actingAs($user)
        ->getJson(route('suggestions', ['field' => 'purchase_source', 'q' => 'SHOW']))
        ->assertOk()
        ->assertExactJson(['suggestions' => ['Showcase Cards', 'Card show']]);

    $this->actingAs($user)
        ->getJson(route('suggestions', 'purchase_source'))
        ->assertExactJson(['suggestions' => ['Card show', 'eBay', 'Showcase Cards']]);
});

test('tags are suggested from every user’s tags', function () {
    Tag::factory()->create(['name' => 'Flip']);
    Tag::factory()->create(['name' => 'PC']);

    $this->actingAs(User::factory()->create())
        ->getJson(route('suggestions', ['field' => 'tag', 'q' => 'fl']))
        ->assertExactJson(['suggestions' => ['Flip']]);
});

test('only known fields can be looked up', function () {
    $this->actingAs(User::factory()->create())
        ->getJson('/suggestions/password')
        ->assertNotFound();
});
