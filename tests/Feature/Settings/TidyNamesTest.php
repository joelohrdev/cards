<?php

use App\Models\Card;
use App\Models\CardSet;
use App\Models\CollectionItem;
use App\Models\Manufacturer;
use App\Models\Parallel;
use App\Models\Player;
use App\Models\Tag;
use App\Models\User;
use App\Models\WantListEntry;
use Inertia\Testing\AssertableInertia as Assert;

test('only admins can tidy up names', function () {
    $member = User::factory()->create();

    $this->actingAs($member)->get(route('tidy-names.index'))->assertForbidden();
    $this->actingAs($member)->post(route('tidy-names.store'), ['type' => 'tag', 'keep' => '1', 'merge' => ['2']])->assertForbidden();
});

test('near-duplicate names are grouped, most used first', function () {
    $griffey = Player::factory()->create(['name' => 'Ken Griffey Jr.', 'sport' => 'baseball']);
    $griffey->cards()->attach(Card::factory()->count(2)->create());
    Player::factory()->create(['name' => 'Ken Griffey Jr', 'sport' => 'baseball']);
    Player::factory()->create(['name' => 'Ken Griffey Jr', 'sport' => 'football']);
    CollectionItem::factory()->count(2)->create(['purchase_source' => 'eBay']);
    CollectionItem::factory()->create(['purchase_source' => 'ebay']);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('tidy-names.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/tidy-names')
            ->has('groups', 2)
            ->where('groups.0.type', 'player')
            ->where('groups.0.records.0.name', 'Ken Griffey Jr.')
            ->where('groups.0.records.0.uses', 2)
            ->has('groups.0.records', 2)
            ->where('groups.1.type', 'purchase_source')
            ->where('groups.1.records.0.name', 'eBay'));
});

test('merging players moves their cards to the kept spelling', function () {
    $keep = Player::factory()->create(['name' => 'Ken Griffey Jr.', 'sport' => 'baseball']);
    $duplicate = Player::factory()->create(['name' => 'Ken Griffey Jr', 'sport' => 'baseball']);
    $both = Card::factory()->create();
    $onlyDuplicate = Card::factory()->create(['name' => 'Ken Griffey Jr']);
    $keep->cards()->attach($both);
    $duplicate->cards()->attach([$both->id, $onlyDuplicate->id]);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('tidy-names.store'), ['type' => 'player', 'keep' => (string) $keep->id, 'merge' => [(string) $duplicate->id]])
        ->assertSessionHasNoErrors();

    expect(Player::query()->whereKey($duplicate->id)->exists())->toBeFalse()
        ->and($keep->cards()->pluck('cards.id')->sort()->values()->all())->toBe([$both->id, $onlyDuplicate->id])
        ->and($onlyDuplicate->fresh()->name)->toBe('Ken Griffey Jr.');
});

test('merging sets merges cards with the same number and parallels with the same name', function () {
    $user = User::factory()->create();
    $topps = Manufacturer::factory()->create(['name' => 'Topps']);
    $keep = CardSet::factory()->for($topps)->create(['sport' => 'baseball', 'year' => 2023, 'name' => 'Chrome']);
    $duplicate = CardSet::factory()->for($topps)->create(['sport' => 'baseball', 'year' => 2023, 'name' => 'CHROME ']);

    $keptCard = Card::factory()->for($keep)->create(['number' => '1']);
    $twinCard = Card::factory()->for($duplicate)->create(['number' => '1', 'subset' => 'Rookie']);
    $movedCard = Card::factory()->for($duplicate)->create(['number' => '2']);
    $keptGold = Parallel::factory()->for($keep)->create(['name' => 'Gold']);
    $twinGold = Parallel::factory()->for($duplicate)->create(['name' => 'gold']);

    $copy = CollectionItem::factory()->for($user)->create(['card_id' => $twinCard->id, 'parallel_id' => $twinGold->id]);
    WantListEntry::factory()->for($user)->create(['card_id' => $keptCard->id, 'parallel_id' => null]);
    WantListEntry::factory()->for($user)->create(['card_id' => $twinCard->id, 'parallel_id' => null]);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('tidy-names.store'), ['type' => 'set', 'keep' => (string) $keep->id, 'merge' => [(string) $duplicate->id]])
        ->assertSessionHasNoErrors();

    expect(CardSet::query()->whereKey($duplicate->id)->exists())->toBeFalse()
        ->and($copy->fresh()->card_id)->toBe($keptCard->id)
        ->and($copy->fresh()->parallel_id)->toBe($keptGold->id)
        ->and($movedCard->fresh()->card_set_id)->toBe($keep->id)
        ->and($keptCard->fresh()->subset)->toBe('Rookie')
        ->and(WantListEntry::query()->where('card_id', $keptCard->id)->count())->toBe(1)
        ->and(Parallel::query()->whereKey($twinGold->id)->exists())->toBeFalse();
});

test('merging tags keeps each card tagged once', function () {
    $keep = Tag::factory()->create(['name' => 'PC']);
    $duplicate = Tag::factory()->create(['name' => 'P.C.']);
    $item = CollectionItem::factory()->create();
    $item->tags()->attach([$keep->id, $duplicate->id]);
    $other = CollectionItem::factory()->create();
    $other->tags()->attach($duplicate->id);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('tidy-names.store'), ['type' => 'tag', 'keep' => (string) $keep->id, 'merge' => [(string) $duplicate->id]])
        ->assertSessionHasNoErrors();

    expect($item->tags()->pluck('tags.id')->all())->toBe([$keep->id])
        ->and($other->tags()->pluck('tags.id')->all())->toBe([$keep->id])
        ->and(Tag::query()->count())->toBe(1);
});

test('merging a text value rewrites every use', function () {
    CollectionItem::factory()->count(2)->create(['purchase_source' => 'eBay']);
    CollectionItem::factory()->create(['purchase_source' => 'ebay ']);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('tidy-names.store'), ['type' => 'purchase_source', 'keep' => base64_encode('eBay'), 'merge' => [base64_encode('ebay ')]])
        ->assertSessionHasNoErrors();

    expect(CollectionItem::query()->pluck('purchase_source')->unique()->all())->toBe(['eBay']);
});

test('names that aren’t spellings of each other can’t be merged', function () {
    $griffey = Player::factory()->create(['name' => 'Ken Griffey Jr.']);
    $trout = Player::factory()->create(['name' => 'Mike Trout']);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('tidy-names.store'), ['type' => 'player', 'keep' => (string) $griffey->id, 'merge' => [(string) $trout->id]])
        ->assertSessionHasErrors('merge');

    expect(Player::query()->count())->toBe(2);
});
