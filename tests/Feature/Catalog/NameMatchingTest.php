<?php

use App\Models\Card;
use App\Models\CardSet;
use App\Models\CollectionItem;
use App\Models\GradingSubmission;
use App\Models\Manufacturer;
use App\Models\Parallel;
use App\Models\Player;
use App\Models\PriceComp;
use App\Models\Sale;
use App\Models\Tag;
use App\Models\Team;
use App\Models\User;

test('a card added by hand reuses the existing spelling of every name', function () {
    $user = User::factory()->create();
    $upperDeck = Manufacturer::factory()->create(['name' => 'Upper Deck']);
    $set = CardSet::factory()->for($upperDeck)->create(['sport' => 'baseball', 'year' => 1989, 'name' => 'Base']);
    $goldParallel = Parallel::factory()->for($set)->create(['name' => 'Gold Refractor']);
    Player::factory()->create(['name' => 'Ken Griffey Jr.', 'sport' => 'baseball']);
    Team::factory()->create(['name' => 'Seattle Mariners', 'sport' => 'baseball']);
    Tag::factory()->create(['name' => 'PC']);
    CollectionItem::factory()->for($user)->create(['purchase_source' => 'eBay']);

    $this->actingAs($user)
        ->post(route('collection.store'), [
            'new_set' => ['sport' => 'baseball', 'year' => 1989, 'manufacturer' => 'UPPERDECK', 'name' => 'base'],
            'card' => ['number' => '1', 'name' => 'ken griffey jr', 'team' => 'seattle mariners', 'subset' => 'Star Rookie'],
            'parallel_name' => 'gold-refractor',
            'tags' => ['pc', 'New Tag'],
            'purchase_source' => 'ebay',
        ])
        ->assertSessionHasNoErrors();

    $item = CollectionItem::query()->latest('id')->first();
    $card = $item->card;

    expect($card->card_set_id)->toBe($set->id)
        ->and(CardSet::query()->where('year', 1989)->count())->toBe(1)
        ->and(Manufacturer::query()->whereLike('name', '%deck%')->count())->toBe(1)
        ->and($card->name)->toBe('Ken Griffey Jr.')
        ->and($card->players->pluck('name')->all())->toBe(['Ken Griffey Jr.'])
        ->and(Player::query()->whereLike('name', '%griffey%')->count())->toBe(1)
        ->and($card->team->name)->toBe('Seattle Mariners')
        ->and(Team::query()->whereLike('name', '%mariners%')->count())->toBe(1)
        ->and($item->parallel_id)->toBe($goldParallel->id)
        ->and($item->tags->pluck('name')->sort()->values()->all())->toBe(['New Tag', 'PC'])
        ->and($item->purchase_source)->toBe('eBay');
});

test('a new set gets the existing brand and set spelling, but stays a new set for another year', function () {
    Manufacturer::factory()->create(['name' => 'Topps']);
    CardSet::factory()->for(Manufacturer::query()->sole())->create(['sport' => 'baseball', 'year' => 2023, 'name' => 'Chrome']);

    $this->actingAs(User::factory()->create())
        ->post(route('sets.store'), ['sport' => 'baseball', 'year' => 2024, 'manufacturer' => 'topps', 'name' => 'CHROME'])
        ->assertSessionHasNoErrors();

    $new = CardSet::query()->where('year', 2024)->sole();
    expect($new->name)->toBe('Chrome')
        ->and($new->manufacturer->name)->toBe('Topps')
        ->and(Manufacturer::query()->count())->toBe(1);
});

test('editing a checklist card matches players and team', function () {
    $set = CardSet::factory()->create(['sport' => 'basketball']);
    $card = Card::factory()->for($set)->create();
    Player::factory()->create(['name' => 'Michael Jordan', 'sport' => 'basketball']);
    Team::factory()->create(['name' => 'Chicago Bulls', 'sport' => 'basketball']);

    $this->actingAs(User::factory()->create())
        ->patch(route('cards.update', $card), ['number' => $card->number, 'name' => $card->name, 'players' => ['MICHAEL JORDAN'], 'team' => 'chicago bulls'])
        ->assertSessionHasNoErrors();

    expect($card->fresh()->players->pluck('name')->all())->toBe(['Michael Jordan'])
        ->and($card->fresh()->team->name)->toBe('Chicago Bulls')
        ->and(Player::query()->count())->toBe(1);
});

test('a parallel or tag that only differs in spelling from an existing one is refused', function () {
    $user = User::factory()->create();
    $set = CardSet::factory()->create();
    Parallel::factory()->for($set)->create(['name' => 'Gold Refractor']);
    Tag::factory()->create(['name' => 'Grading candidate']);

    $this->actingAs($user)
        ->post(route('sets.parallels.store', $set), ['name' => 'gold-refractor'])
        ->assertSessionHasErrors(['name' => 'This set already has the “Gold Refractor” parallel.']);

    $this->actingAs($user)
        ->post(route('tags.store'), ['name' => 'grading  candidate', 'color' => 'slate'])
        ->assertSessionHasErrors(['name' => 'There’s already a “Grading candidate” tag.']);
});

test('buyer, comp source and grading service level take the existing spelling', function () {
    $user = User::factory()->create();
    GradingSubmission::factory()->for($user)->create(['service_level' => 'Value Plus']);
    $item = CollectionItem::factory()->for($user)->create();
    PriceComp::factory()->for($user)->create(['card_id' => $item->card_id, 'source' => 'eBay']);
    Sale::factory()->for($user)->create(['buyer' => 'Card Shop Joe']);
    $forSale = CollectionItem::factory()->for($user)->create();

    $this->actingAs($user)
        ->post(route('sales.store'), ['collection_item_id' => $forSale->id, 'platform' => 'ebay', 'sold_on' => '2026-03-14', 'sale_price' => '10', 'buyer' => 'card shop joe'])
        ->assertSessionHasNoErrors();

    $this->actingAs($user)
        ->post(route('grading.store'), ['grader' => 'PSA', 'service_level' => 'value plus'])
        ->assertSessionHasNoErrors();

    $this->actingAs($user)
        ->post(route('collection.comps.store', $item), ['price' => '10', 'sold_on' => '2026-02-01', 'source' => 'EBAY'])
        ->assertSessionHasNoErrors();

    expect(GradingSubmission::query()->pluck('service_level')->unique()->all())->toBe(['Value Plus'])
        ->and(PriceComp::query()->pluck('source')->unique()->all())->toBe(['eBay'])
        ->and(Sale::query()->pluck('buyer')->unique()->all())->toBe(['Card Shop Joe']);
});

test('a set can go by its brand alone, but needs a brand or a name', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('sets.store'), ['sport' => 'baseball', 'year' => 1986])
        ->assertSessionHasErrors(['manufacturer' => 'Enter a brand or a set name.']);

    $this->actingAs($user)
        ->post(route('sets.store'), ['sport' => 'baseball', 'year' => 1986, 'manufacturer' => 'Fleer'])
        ->assertSessionHasNoErrors();

    $fleer = CardSet::query()->where('year', 1986)->sole();
    expect($fleer->name)->toBe('')
        ->and($fleer->full_name)->toBe('1986 Fleer');

    $this->actingAs($user)
        ->post(route('collection.store'), [
            'new_set' => ['sport' => 'baseball', 'year' => 1986, 'manufacturer' => 'fleer'],
            'card' => ['number' => '57', 'name' => 'Michael Jordan'],
        ])
        ->assertSessionHasNoErrors();

    $this->actingAs($user)
        ->post(route('collection.store'), [
            'new_set' => ['sport' => 'baseball', 'year' => 1987],
            'card' => ['number' => '1', 'name' => 'Someone'],
        ])
        ->assertSessionHasErrors(['new_set.manufacturer' => 'Enter a brand or a set name.']);

    expect(CardSet::query()->where('year', 1986)->count())->toBe(1)
        ->and(CollectionItem::query()->sole()->card->card_set_id)->toBe($fleer->id);
});
