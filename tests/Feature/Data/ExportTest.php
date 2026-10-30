<?php

use App\Enums\Grader;
use App\Enums\ItemStatus;
use App\Models\Card;
use App\Models\CardSet;
use App\Models\CollectionItem;
use App\Models\Manufacturer;
use App\Models\StorageLocation;
use App\Models\Tag;
use App\Models\User;
use App\Support\Export\CollectionCsv;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @return list<array<string, string>>
 */
function csvRows(string $content): array
{
    $lines = array_map(fn (string $line): array => str_getcsv($line, ',', '"', ''), explode("\n", trim(preg_replace('/^\xEF\xBB\xBF/', '', $content))));
    $headers = array_shift($lines);

    return array_map(fn (array $line): array => array_combine($headers, $line), $lines);
}

test('the collection csv lists every card, including ones a teammate added', function () {
    $user = User::factory()->create();
    $set = CardSet::factory()->create(['year' => 1989, 'name' => 'Base', 'manufacturer_id' => Manufacturer::factory()->create(['name' => 'Upper Deck'])->id]);
    $card = Card::factory()->for($set)->create(['number' => '1', 'name' => 'Ken Griffey Jr.', 'is_rookie' => true]);
    $box = StorageLocation::factory()->for($user)->create(['name' => 'Box 4', 'parent_id' => StorageLocation::factory()->for($user)->create(['name' => 'Closet'])->id]);
    $item = CollectionItem::factory()->for($user)->for($card)->graded(Grader::Psa, '9')->create([
        'purchase_price' => 120050,
        'estimated_value' => 200000,
        'storage_location_id' => $box->id,
        'notes' => '=HYPERLINK("http://example.test")',
    ]);
    $item->tags()->attach(Tag::factory()->for($user)->create(['name' => 'PC']));
    CollectionItem::factory()->for($user)->status(ItemStatus::Sold)->create();
    $teammates = CollectionItem::factory()->create();

    $response = $this->actingAs($user)->get(route('exports.collection'));

    $response->assertOk()->assertDownload('card-collection-'.now()->format('Y-m-d').'.csv');
    $rows = csvRows($response->streamedContent());

    expect($rows)->toHaveCount(3)
        ->and(array_keys($rows[0]))->toBe(CollectionCsv::HEADERS)
        ->and($rows[0])->toMatchArray([
            'ID' => (string) $item->id,
            'Year' => '1989',
            'Brand' => 'Upper Deck',
            'Set' => 'Base',
            'Card #' => '1',
            'Name' => 'Ken Griffey Jr.',
            'RC' => 'Yes',
            'Graded' => 'Yes',
            'Grader' => 'PSA',
            'Grade' => '9',
            'Status' => 'Owned',
            'Paid' => '1200.50',
            'Value' => '2000.00',
            'Location' => 'Closet / Box 4',
            'Tags' => 'PC',
            'Notes' => '\'=HYPERLINK("http://example.test")',
        ])
        ->and($rows[1]['Status'])->toBe('Sold')
        ->and($rows[2]['ID'])->toBe((string) $teammates->id);
});

test('the insurance report lists in-hand cards by value with totals', function () {
    $user = User::factory()->create();
    $cheap = CollectionItem::factory()->for($user)->create(['estimated_value' => 500, 'purchase_price' => 100]);
    $unvalued = CollectionItem::factory()->for($user)->create(['estimated_value' => null, 'purchase_price' => 200]);
    $valuable = CollectionItem::factory()->for($user)->status(ItemStatus::Listed)->create(['estimated_value' => 90000, 'purchase_price' => 300]);
    CollectionItem::factory()->for($user)->status(ItemStatus::Sold)->create();
    $teammates = CollectionItem::factory()->create(['estimated_value' => 1000, 'purchase_price' => 400]);

    $this->actingAs($user)
        ->get(route('exports.insurance'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('exports/insurance')
            ->where('status', 'in-hand')
            ->where('items', fn ($items) => collect($items)->pluck('id')->all() === [$valuable->id, $teammates->id, $cheap->id, $unvalued->id])
            ->where('totals', ['count' => 4, 'value' => 91500, 'cost' => 1000, 'unvalued' => 1]));

    $this->actingAs($user)
        ->get(route('exports.insurance', ['status' => 'sold']))
        ->assertInertia(fn (Assert $page) => $page->has('items', 1)->where('items.0.status_label', 'Sold'));
});

test('the export page loads', function () {
    $user = User::factory()->create();
    CollectionItem::factory()->for($user)->count(2)->create();

    $this->actingAs($user)
        ->get(route('exports.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('exports/index')->where('counts', ['all' => 2, 'in_hand' => 2]));
});

test('guests cannot export', function () {
    $this->get(route('exports.collection'))->assertRedirect(route('login'));
});
