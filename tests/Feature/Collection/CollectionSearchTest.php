<?php

use App\Enums\Grader;
use App\Enums\Sport;
use App\Models\Card;
use App\Models\CardSet;
use App\Models\CollectionItem;
use App\Models\Manufacturer;
use App\Models\Parallel;
use App\Models\Player;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Build a card in a named set for the given user.
 *
 * @param  array<string, mixed>  $cardAttributes
 * @param  array<string, mixed>  $itemAttributes
 */
function ownCard(User $user, int $year, string $brand, string $set, string $player, array $cardAttributes = [], array $itemAttributes = []): CollectionItem
{
    $cardSet = CardSet::query()->firstOrCreate([
        'year' => $year,
        'name' => $set,
        'sport' => Sport::Baseball,
        'manufacturer_id' => Manufacturer::query()->firstOrCreate(['name' => $brand])->id,
    ]);

    $card = Card::factory()->for($cardSet)->create(['name' => $player, ...$cardAttributes]);
    $card->players()->attach(Player::query()->firstOrCreate(['name' => $player, 'sport' => Sport::Baseball]));

    return CollectionItem::factory()->for($user)->for($card)->create($itemAttributes);
}

/**
 * @return list<int>
 */
function searchIds(User $user, array $params): array
{
    $ids = [];

    test()->actingAs($user)
        ->get(route('collection.index', $params))
        ->assertOk()
        ->assertInertia(function (Assert $page) use (&$ids) {
            $ids = collect($page->toArray()['props']['items']['data'])->pluck('id')->all();
        });

    return $ids;
}

test('free text combines year, brand, player and grade shorthand', function () {
    $user = User::factory()->create();
    $jordanPsa = ownCard($user, 1986, 'Fleer', 'Base', 'Michael Jordan', ['number' => '57', 'is_rookie' => true], ['is_graded' => true, 'grader' => Grader::Psa, 'grade' => '8']);
    $jordanRaw = ownCard($user, 1986, 'Fleer', 'Stickers', 'Michael Jordan', ['number' => '8']);
    ownCard($user, 1989, 'Upper Deck', 'Base', 'Ken Griffey Jr.', ['number' => '1', 'is_rookie' => true]);

    expect(searchIds($user, ['q' => '1986 fleer jordan']))->toEqualCanonicalizing([$jordanPsa->id, $jordanRaw->id])
        ->and(searchIds($user, ['q' => 'jordan psa 8']))->toBe([$jordanPsa->id])
        ->and(searchIds($user, ['q' => 'jordan raw']))->toBe([$jordanRaw->id])
        ->and(searchIds($user, ['q' => '#57']))->toBe([$jordanPsa->id])
        ->and(searchIds($user, ['q' => 'rc']))->toHaveCount(2)
        ->and(searchIds($user, ['q' => 'griffey 1986']))->toBe([]);
});

test('free text matches parallels, print runs and tags', function () {
    $user = User::factory()->create();
    $item = ownCard($user, 2023, 'Topps', 'Chrome', 'Elly De La Cruz');
    $parallel = Parallel::factory()->create(['card_set_id' => $item->card->card_set_id, 'name' => 'Gold Refractor', 'print_run' => 50]);
    $item->update(['parallel_id' => $parallel->id]);
    $item->tags()->attach($user->tags()->create(['name' => 'Reds PC', 'color' => 'red']));
    ownCard($user, 2023, 'Topps', 'Chrome', 'Gunnar Henderson');

    expect(searchIds($user, ['q' => 'gold']))->toBe([$item->id])
        ->and(searchIds($user, ['q' => '/50']))->toBe([$item->id])
        ->and(searchIds($user, ['q' => 'reds']))->toBe([$item->id]);
});

test('structured filters narrow by sport, year range, value and flags', function () {
    $user = User::factory()->create();
    $vintage = ownCard($user, 1955, 'Topps', 'Base', 'Roberto Clemente', ['is_rookie' => true], ['estimated_value' => 500000]);
    $modern = ownCard($user, 2020, 'Topps', 'Update', 'Luis Robert', [], ['estimated_value' => 500]);

    expect(searchIds($user, ['year_from' => 1950, 'year_to' => 1959]))->toBe([$vintage->id])
        ->and(searchIds($user, ['min_value' => '100']))->toBe([$vintage->id])
        ->and(searchIds($user, ['max_value' => '$10']))->toBe([$modern->id])
        ->and(searchIds($user, ['rookie' => 1]))->toBe([$vintage->id])
        ->and(searchIds($user, ['sort' => 'value_asc']))->toBe([$modern->id, $vintage->id]);
});

test('quick search returns matching cards from the whole collection, sets and players as JSON', function () {
    $user = User::factory()->create();
    ownCard($user, 1989, 'Upper Deck', 'Base', 'Ken Griffey Jr.');
    ownCard(User::factory()->create(), 1989, 'Upper Deck', 'Base', 'Ken Griffey Jr.');
    ownCard($user, 1989, 'Upper Deck', 'Base', 'Nolan Ryan');

    $this->actingAs($user)
        ->getJson(route('search', ['q' => 'griffey']))
        ->assertOk()
        ->assertJsonCount(2, 'items')
        ->assertJsonPath('total', 2)
        ->assertJsonPath('players.0.name', 'Ken Griffey Jr.');
});
