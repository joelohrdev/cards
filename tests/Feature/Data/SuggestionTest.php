<?php

use App\Models\CardSet;
use App\Models\CollectionItem;
use App\Models\Parallel;
use App\Models\Player;
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
        ->assertExactJson(['suggestions' => ['Showcase Cards', 'Card show'], 'close' => []]);

    $this->actingAs($user)
        ->getJson(route('suggestions', 'purchase_source'))
        ->assertExactJson(['suggestions' => ['Card show', 'eBay', 'Showcase Cards'], 'close' => []]);
});

test('tags are suggested from every user’s tags', function () {
    Tag::factory()->create(['name' => 'Flip']);
    Tag::factory()->create(['name' => 'PC']);

    $this->actingAs(User::factory()->create())
        ->getJson(route('suggestions', ['field' => 'tag', 'q' => 'fl']))
        ->assertExactJson(['suggestions' => ['Flip'], 'close' => []]);
});

test('players already in the catalog are suggested', function () {
    Player::factory()->create(['name' => 'Michael Jordan']);
    Player::factory()->create(['name' => 'Scottie Pippen']);

    $this->actingAs(User::factory()->create())
        ->getJson(route('suggestions', ['field' => 'player', 'q' => 'pip']))
        ->assertExactJson(['suggestions' => ['Scottie Pippen'], 'close' => []]);
});

test('a set’s own parallels are suggested', function () {
    $set = CardSet::factory()->create();
    Parallel::factory()->for($set)->create(['name' => 'Gold Refractor']);
    Parallel::factory()->for($set)->create(['name' => 'Refractor']);
    Parallel::factory()->for(CardSet::factory())->create(['name' => 'Red Refractor']);

    $this->actingAs(User::factory()->create())
        ->getJson(route('suggestions', ['field' => 'parallel', 'set' => $set->id, 'q' => 'refr']))
        ->assertExactJson(['suggestions' => ['Refractor', 'Gold Refractor'], 'close' => []]);
});

test('a likely typo gets “did you mean” suggestions when nothing matches', function () {
    Player::factory()->create(['name' => 'Ken Griffey Jr.']);
    Player::factory()->create(['name' => 'Josh Bell']);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson(route('suggestions', ['field' => 'player', 'q' => 'Ken Griffy Jr']))
        ->assertExactJson(['suggestions' => [], 'close' => ['Ken Griffey Jr.']]);

    $this->actingAs($user)
        ->getJson(route('suggestions', ['field' => 'player', 'q' => 'Josh Hall']))
        ->assertExactJson(['suggestions' => [], 'close' => []]);
});

test('only known fields can be looked up', function () {
    $this->actingAs(User::factory()->create())
        ->getJson('/suggestions/password')
        ->assertNotFound();
});
