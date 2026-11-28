<?php

use App\Models\Card;
use App\Models\CardSet;
use App\Models\CollectionItem;
use App\Models\ItemImage;
use App\Models\Manufacturer;
use App\Models\Player;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config(['services.anthropic.key' => 'sk-test', 'services.anthropic.model' => 'claude-sonnet-5-5']);
    Storage::fake(config('filesystems.card_photos'));
    Http::preventStrayRequests();

    $this->topps = Manufacturer::factory()->create(['name' => 'Topps']);
    $set1966 = CardSet::factory()->for($this->topps)->create(['year' => 1966, 'name' => '', 'sport' => 'baseball']);
    $this->leaders = Card::factory()->for($set1966)->create(['number' => '215', 'name' => 'N.L. Batting Leaders (Bob Clemente, Hank Aaron, Willie Mays)']);
    $this->leaders->players()->attach(Player::factory()->create(['name' => 'Bob Clemente', 'sport' => 'baseball']));
    $this->mays1966 = Card::factory()->for($set1966)->create(['number' => '1', 'name' => 'Willie Mays']);
});

/**
 * A copy of the leaders card with a saved front photo.
 */
function leadersCopy(Card $leaders): CollectionItem
{
    $item = CollectionItem::factory()->for($leaders)->create();
    $image = ItemImage::factory()->for($item)->create();
    Storage::disk(config('filesystems.card_photos'))->put($image->path, 'photo');

    return $item;
}

/**
 * A Messages API reply reading the given card details.
 *
 * @param  array<string, mixed>  $card
 */
function photoReading(array $card): array
{
    return [
        'id' => 'msg_test',
        'type' => 'message',
        'role' => 'assistant',
        'content' => [['type' => 'text', 'text' => json_encode(['sport' => 'baseball', 'manufacturer' => 'Topps', 'confidence' => 'high', ...$card])]],
        'stop_reason' => 'end_turn',
    ];
}

test('a dry run lists copies on the wrong card without moving them', function () {
    $wrong = leadersCopy($this->leaders);
    Http::fake(['api.anthropic.com/*' => Http::response(photoReading(['year' => 1966, 'number' => '1', 'name' => 'Willie Mays']))]);

    $this->artisan('cards:recheck-photos')
        ->expectsOutputToContain('1966 Topps #1 Willie Mays')
        ->expectsOutputToContain('Run again with --apply')
        ->assertSuccessful();

    expect($wrong->refresh()->card_id)->toBe($this->leaders->id);
});

test('applying moves wrong copies to the card in their photos and leaves the rest', function () {
    $right = leadersCopy($this->leaders);
    $onExistingCard = leadersCopy($this->leaders);
    $onNewCard = leadersCopy($this->leaders);
    $unclear = leadersCopy($this->leaders);

    Http::fakeSequence('api.anthropic.com/*')
        ->push(photoReading(['year' => 1966, 'number' => '215', 'name' => 'N.L. Batting Leaders']))
        ->push(photoReading(['year' => 1966, 'number' => '1', 'name' => 'Willie Mays']))
        ->push(photoReading(['year' => 1964, 'number' => '440', 'name' => 'Bob Clemente']))
        ->push(photoReading(['year' => 1964, 'number' => null, 'name' => 'Bob Clemente']));

    $this->artisan('cards:recheck-photos', ['--apply' => true])->assertSuccessful();

    $newCard = $onNewCard->refresh()->card;

    expect($right->refresh()->card_id)->toBe($this->leaders->id)
        ->and($onExistingCard->refresh()->card_id)->toBe($this->mays1966->id)
        ->and($newCard->number)->toBe('440')
        ->and($newCard->cardSet->year)->toBe(1964)
        ->and($newCard->cardSet->manufacturer_id)->toBe($this->topps->id)
        ->and($newCard->players->pluck('name')->all())->toBe(['Bob Clemente'])
        ->and($unclear->refresh()->card_id)->toBe($this->leaders->id)
        ->and($this->leaders->refresh()->name)->toBe('N.L. Batting Leaders (Bob Clemente, Hank Aaron, Willie Mays)');
});

test('only the chosen checklist card’s copies are rechecked', function () {
    $other = CollectionItem::factory()->for($this->mays1966)->create();
    ItemImage::factory()->for($other)->create();
    $wrong = leadersCopy($this->leaders);
    Http::fake(['api.anthropic.com/*' => Http::response(photoReading(['year' => 1966, 'number' => '1', 'name' => 'Willie Mays']))]);

    $this->artisan('cards:recheck-photos', ['--apply' => true, '--card' => [$this->leaders->id]])->assertSuccessful();

    Http::assertSentCount(1);
    expect($wrong->refresh()->card_id)->toBe($this->mays1966->id);
});
