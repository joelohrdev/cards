<?php

use App\Enums\ItemStatus;
use App\Models\Card;
use App\Models\CardSet;
use App\Models\CollectionItem;
use App\Models\Parallel;
use App\Models\User;
use App\Models\WantListEntry;
use Inertia\Testing\AssertableInertia as Assert;

test('a set can be created with a pasted checklist', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('sets.store'), [
            'sport' => 'basketball',
            'year' => 1986,
            'manufacturer' => 'Fleer',
            'name' => 'Base',
            'card_count' => 132,
            'checklist' => "57 Michael Jordan - Chicago Bulls RC\n58 Larry Bird / Kevin McHale - Boston Celtics",
        ])
        ->assertSessionHasNoErrors();

    $set = CardSet::query()->sole();
    $jordan = $set->cards()->where('number', '57')->sole();
    $duo = $set->cards()->where('number', '58')->sole();

    expect($set->full_name)->toBe('1986 Fleer Base')
        ->and($jordan->is_rookie)->toBeTrue()
        ->and($jordan->team->name)->toBe('Chicago Bulls')
        ->and($duo->players->pluck('name')->sort()->values()->all())->toBe(['Kevin McHale', 'Larry Bird']);
});

test('creating a set that already exists reuses it', function () {
    $user = User::factory()->create();
    $payload = ['sport' => 'baseball', 'year' => 1952, 'manufacturer' => 'Topps', 'name' => 'Base'];

    $this->actingAs($user)->post(route('sets.store'), $payload);
    $this->actingAs($user)->post(route('sets.store'), $payload)->assertSessionHasNoErrors();

    expect(CardSet::query()->count())->toBe(1);
});

test('the set page shows the checklist with the shared collection’s ownership and wants', function () {
    $user = User::factory()->create();
    $set = CardSet::factory()->create();
    $owned = Card::factory()->for($set)->create(['number' => '1']);
    $ownedByTeammate = Card::factory()->for($set)->create(['number' => '2']);
    $wanted = Card::factory()->for($set)->create(['number' => '3']);
    CollectionItem::factory()->for($user)->for($owned)->create();
    CollectionItem::factory()->for($ownedByTeammate)->create();
    WantListEntry::factory()->for($wanted)->create();

    $this->actingAs($user)
        ->get(route('sets.show', $set))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('catalog/sets/show')
            ->has('cards', 3)
            ->where('cards.0.id', $owned->id)
            ->has('cards.0.owned', 1)
            ->has('cards.1.owned', 1)
            ->has('cards.2.owned', 0)
            ->where('cards.2.wanted', true));
});

test('the set index reports completion for the collection', function () {
    $user = User::factory()->create();
    $set = CardSet::factory()->create();
    $cards = Card::factory()->for($set)->count(4)->create();
    CollectionItem::factory()->for($user)->for($cards[0])->create();
    CollectionItem::factory()->for($user)->for($cards[1])->status(ItemStatus::Sold)->create();

    $this->actingAs($user)
        ->get(route('sets.index', ['mine' => 1]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('catalog/sets/index')
            ->has('sets.data', 1)
            ->where('sets.data.0.cards_count', 4)
            ->where('sets.data.0.owned_count', 1));
});

test('checklist mode adds one copy of each ticked card and clears matching wants', function () {
    $user = User::factory()->create();
    $set = CardSet::factory()->create();
    $parallel = Parallel::factory()->for($set)->create();
    $cards = Card::factory()->for($set)->count(3)->create();
    WantListEntry::factory()->for($user)->for($cards[0])->create();

    $this->actingAs($user)
        ->post(route('sets.owned.store', $set), [
            'card_ids' => [$cards[0]->id, $cards[1]->id],
            'parallel_id' => $parallel->id,
            'raw_condition' => 'ex',
            'tags' => ['Set build'],
        ])
        ->assertSessionHasNoErrors();

    expect($user->collectionItems()->count())->toBe(2)
        ->and($user->collectionItems()->pluck('parallel_id')->unique()->all())->toBe([$parallel->id])
        ->and($user->wantListEntries()->count())->toBe(0);
});

test('checklist mode only accepts cards from the same set', function () {
    $set = CardSet::factory()->create();

    $this->actingAs(User::factory()->create())
        ->post(route('sets.owned.store', $set), ['card_ids' => [Card::factory()->create()->id]])
        ->assertSessionHasErrors('card_ids.0');
});

test('pasting more checklist lines updates and adds cards', function () {
    $set = CardSet::factory()->create();
    Card::factory()->for($set)->create(['number' => '1', 'name' => 'Old Name']);

    $this->actingAs(User::factory()->create())
        ->post(route('sets.checklist.store', $set), ['checklist' => "1 New Name\n2 Second Card SP"])
        ->assertSessionHasNoErrors();

    expect($set->cards()->orderBy('sort_order')->pluck('name')->all())->toBe(['New Name', 'Second Card'])
        ->and($set->cards()->where('number', '2')->sole()->is_short_print)->toBeTrue();
});

test('an unparseable checklist explains the expected format', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('sets.checklist.store', CardSet::factory()->create()), ['checklist' => '   '])
        ->assertSessionHasErrors('checklist');
});

test('sets and checklist cards someone owns cannot be deleted', function () {
    $card = Card::factory()->create();
    CollectionItem::factory()->for($card)->create();
    $user = User::factory()->create();

    $this->actingAs($user)->delete(route('cards.destroy', $card))->assertSessionHasErrors('card');
    $this->actingAs($user)->delete(route('sets.destroy', $card->cardSet))->assertSessionHasErrors('set');

    expect($card->fresh())->not->toBeNull();
});

test('an unowned set and card can be deleted', function () {
    $card = Card::factory()->create();
    $set = $card->cardSet;
    $user = User::factory()->create();

    $this->actingAs($user)->delete(route('cards.destroy', $card))->assertSessionHasNoErrors();
    $this->actingAs($user)->delete(route('sets.destroy', $set))->assertRedirect(route('sets.index'));

    expect(CardSet::query()->count())->toBe(0);
});

test('a checklist card’s details and players can be corrected', function () {
    $card = Card::factory()->create();

    $this->actingAs(User::factory()->create())
        ->patch(route('cards.update', $card), [
            'number' => '7',
            'name' => 'Mickey Mantle',
            'players' => ['Mickey Mantle'],
            'team' => 'New York Yankees',
            'is_rookie' => false,
            'is_short_print' => true,
        ])
        ->assertSessionHasNoErrors();

    $card->refresh();

    expect($card->number)->toBe('7')
        ->and($card->sort_order)->toBe(7)
        ->and($card->team->name)->toBe('New York Yankees')
        ->and($card->players->pluck('name')->all())->toBe(['Mickey Mantle']);
});

test('parallels can be added once per set and removed', function () {
    $set = CardSet::factory()->create();
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('sets.parallels.store', $set), ['name' => 'Gold', 'print_run' => 50])->assertSessionHasNoErrors();
    $this->actingAs($user)->post(route('sets.parallels.store', $set), ['name' => 'Gold'])->assertSessionHasErrors('name');

    $parallel = $set->parallels()->sole();
    $this->actingAs($user)->delete(route('sets.parallels.destroy', [$set, $parallel]))->assertSessionHasNoErrors();

    expect($set->parallels()->count())->toBe(0);
});

test('catalog lookups find cards by words and number, and list a whole set', function () {
    $set = CardSet::factory()->create(['year' => 1989, 'name' => 'Base']);
    $griffey = Card::factory()->for($set)->create(['number' => '1', 'name' => 'Ken Griffey Jr.']);
    Card::factory()->for($set)->count(30)->create();

    $this->actingAs(User::factory()->create())
        ->getJson(route('catalog.lookup.cards', ['q' => '1989 griffey']))
        ->assertOk()
        ->assertJsonCount(1, 'cards')
        ->assertJsonPath('cards.0.id', $griffey->id);

    $this->actingAs(User::factory()->create())
        ->getJson(route('catalog.lookup.cards', ['set' => $set->id]))
        ->assertJsonCount(31, 'cards');

    $this->actingAs(User::factory()->create())
        ->getJson(route('catalog.lookup.sets', ['q' => '1989']))
        ->assertJsonPath('sets.0.id', $set->id);
});
