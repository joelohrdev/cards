<?php

use App\Enums\Grader;
use App\Enums\ImportStatus;
use App\Enums\LocationType;
use App\Enums\RawCondition;
use App\Enums\Sport;
use App\Jobs\ProcessImport;
use App\Models\CardSet;
use App\Models\CollectionItem;
use App\Models\Import;
use App\Models\StorageLocation;
use App\Models\Tag;
use App\Models\User;
use App\Support\Export\CollectionCsv;
use App\Support\Import\ImportFields;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Storage::fake('local');
});

/**
 * Store the fixture (or given CSV text) on the fake disk and create a mapped, running import for it.
 *
 * @param  array<string, mixed>  $attributes
 */
function startedImport(User $user, ?string $csv = null, array $attributes = []): Import
{
    $csv ??= file_get_contents(base_path('tests/fixtures/imports/cards.csv'));
    $path = 'imports/'.fake()->uuid().'.csv';
    Storage::disk('local')->put($path, $csv);
    $headers = str_getcsv(strtok($csv, "\n"), ',', '"', '');
    $tag = Tag::factory()->for($user)->create(['name' => 'Import 2026-10-29']);

    $import = Import::factory()->for($user)->create([
        'path' => $path,
        'headers' => $headers,
        'mapping' => ImportFields::guessMapping($headers),
        'defaults' => ['sport' => 'baseball', 'tags' => ['From spreadsheet']],
        'status' => ImportStatus::Processing,
        'total_rows' => substr_count(trim($csv), "\n"),
        ...$attributes,
    ]);
    $import->tag()->associate($tag)->save();

    return $import;
}

test('uploading a csv stores it privately and opens the mapping page with guessed columns', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->createWithContent('my cards.csv', file_get_contents(base_path('tests/fixtures/imports/cards.csv')));

    $response = $this->actingAs($user)->post(route('imports.store'), ['file' => $file]);

    $import = Import::query()->sole();
    $response->assertRedirect(route('imports.mapping', $import));
    Storage::disk('local')->assertExists($import->path);

    expect($import->path)->toStartWith('imports/')
        ->and($import->user_id)->toBe($user->id)
        ->and($import->original_filename)->toBe('my cards.csv')
        ->and($import->total_rows)->toBe(6)
        ->and($import->headers[0])->toBe('Yr');

    $this->actingAs($user)
        ->get(route('imports.mapping', $import))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('imports/mapping')
            ->has('preview', 5)
            ->where('preview.0.4', 'Ken Griffey Jr.')
            ->where('mapping.year', 0)
            ->where('mapping.manufacturer', 1)
            ->where('mapping.set', 2)
            ->where('mapping.number', 3)
            ->where('mapping.name', 4)
            ->where('mapping.serial_number', 7)
            ->where('mapping.is_rookie', 8)
            ->where('mapping.grade', 10)
            ->where('mapping.cert_number', 11)
            ->where('mapping.raw_condition', 12)
            ->where('mapping.purchase_price', 13)
            ->where('mapping.estimated_value', 15)
            ->where('mapping.location', 16)
            ->where('mapping.quantity', 19)
            ->where('mapping.sport', null));
});

test('uploads must be a csv within the size limit', function (UploadedFile $file, string $message) {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('imports.store'), ['file' => $file])
        ->assertSessionHasErrors(['file' => $message]);

    expect(Import::query()->count())->toBe(0);
})->with([
    'not a csv' => [fn () => UploadedFile::fake()->image('card.png'), 'Upload a CSV file. In Excel or Google Sheets use “Save as / Download → CSV”.'],
    'too large' => [fn () => UploadedFile::fake()->create('huge.csv', 20481, 'text/csv'), 'That file is over 20 MB. Split it into smaller files and import each one.'],
    'headers only' => [fn () => UploadedFile::fake()->createWithContent('empty.csv', "Year,Set,Number\n"), 'That file has no rows to import. The first row should be column headings, with one card per row below it.'],
]);

test('header names are matched to fields, including the collection export’s own headings', function () {
    $guess = ImportFields::guessMapping(CollectionCsv::HEADERS);
    $column = fn (string $header): int => array_search($header, CollectionCsv::HEADERS, true);

    expect($guess)->toMatchArray([
        'year' => $column('Year'),
        'sport' => $column('Sport'),
        'manufacturer' => $column('Brand'),
        'set' => $column('Set'),
        'number' => $column('Card #'),
        'name' => $column('Name'),
        'players' => $column('Players'),
        'parallel' => $column('Parallel'),
        'serial_number' => $column('Serial'),
        'print_run' => $column('Print run'),
        'is_memorabilia' => $column('Relic'),
        'grader' => $column('Grader'),
        'grade' => $column('Grade'),
        'status' => $column('Status'),
        'purchase_price' => $column('Paid'),
        'estimated_value' => $column('Value'),
        'purchase_source' => $column('Source'),
        'location' => $column('Location'),
        'storage_position' => $column('Position'),
        'tags' => $column('Tags'),
    ]);

    expect(ImportFields::guessMapping(['No', 'Year Released', 'Manufacturer', 'Variation', 'Print Run /', 'Company', 'Value (est.)', 'Quantity']))
        ->toMatchArray([
            'number' => 0,
            'year' => 1,
            'manufacturer' => 2,
            'parallel' => 3,
            'print_run' => 4,
            'grader' => 5,
            'estimated_value' => 6,
            'quantity' => 7,
        ]);
});

test('saving the mapping tags the import and queues it', function () {
    Queue::fake();
    $user = User::factory()->create();
    $location = StorageLocation::factory()->for($user)->create();
    $import = Import::factory()->for($user)->create(['headers' => ['Year', 'Set', 'Number', 'Player']]);

    $this->actingAs($user)
        ->put(route('imports.update', $import), [
            'mapping' => ['year' => 0, 'set' => 1, 'number' => 2, 'name' => 3],
            'defaults' => ['sport' => 'hockey', 'tags' => ['Binder A', ' '], 'storage_location_id' => $location->id],
        ])
        ->assertRedirect(route('imports.show', $import));

    $import->refresh();

    expect($import->status)->toBe(ImportStatus::Processing)
        ->and($import->mapping)->toMatchArray(['year' => 0, 'set' => 1, 'number' => 2, 'name' => 3, 'sport' => null])
        ->and($import->defaults)->toBe(['sport' => 'hockey', 'tags' => ['Binder A'], 'storage_location_id' => $location->id])
        ->and($import->tag->name)->toBe('Import '.now()->toDateString());

    Queue::assertPushed(ProcessImport::class, fn (ProcessImport $job) => $job->import->is($import));
});

test('the mapping needs year, set and number columns plus a sport', function () {
    Queue::fake();
    $user = User::factory()->create();
    $import = Import::factory()->for($user)->create(['headers' => ['Year', 'Set', 'Number']]);

    $this->actingAs($user)
        ->put(route('imports.update', $import), [
            'mapping' => ['year' => 0, 'set' => 7],
            'defaults' => ['storage_location_id' => 999999],
        ])
        ->assertSessionHasErrors(['mapping.set', 'mapping.number', 'defaults.sport', 'defaults.storage_location_id']);

    expect($import->refresh()->status)->toBe(ImportStatus::Pending);
    Queue::assertNothingPushed();
});

test('the import job creates sets, cards and items and records failed rows', function () {
    $user = User::factory()->create();
    $import = startedImport($user);

    ProcessImport::dispatchSync($import);

    $import->refresh();

    expect($import->status)->toBe(ImportStatus::Completed)
        ->and($import->completed_at)->not->toBeNull()
        ->and($import->processed_rows)->toBe(6)
        ->and($import->imported_rows)->toBe(2)
        ->and($import->failures)->toBe([
            ['row' => 4, 'message' => 'Couldn’t read the price paid “abc” as an amount.'],
            ['row' => 5, 'message' => 'Missing the year.'],
            ['row' => 6, 'message' => 'Missing the card number.'],
            ['row' => 7, 'message' => 'The grade “10” has no grading company. Map a grading company column, or write grades like “PSA 10”.'],
        ]);

    $griffey = CollectionItem::query()->whereRelation('card', 'name', 'Ken Griffey Jr.')->sole();

    expect($griffey->user_id)->toBe($user->id)
        ->and($griffey->card->cardSet->year)->toBe(1989)
        ->and($griffey->card->cardSet->sport)->toBe(Sport::Baseball)
        ->and($griffey->card->cardSet->manufacturer->name)->toBe('Upper Deck')
        ->and($griffey->card->is_rookie)->toBeTrue()
        ->and($griffey->card->team->name)->toBe('Seattle Mariners')
        ->and($griffey->is_graded)->toBeTrue()
        ->and($griffey->grader)->toBe(Grader::Psa)
        ->and($griffey->grade)->toBe('9')
        ->and($griffey->cert_number)->toBe('12345678')
        ->and($griffey->purchase_price)->toBe(120050)
        ->and($griffey->estimated_value)->toBe(200000)
        ->and($griffey->purchase_date->toDateString())->toBe('2021-03-15')
        ->and($griffey->notes)->toBe('Nice centering')
        ->and($griffey->storageLocation->path())->toBe('Closet / Box 4')
        ->and($griffey->storageLocation->type)->toBe(LocationType::Box)
        ->and($griffey->tags->pluck('name')->sort()->values()->all())->toBe(['From spreadsheet', 'Import 2026-10-29', 'Mariners', 'PC']);

    $wembys = CollectionItem::query()->whereRelation('card', 'name', 'Victor Wembanyama')->get();
    $wemby = $wembys->first();

    expect($wembys)->toHaveCount(2)
        ->and($wembys->pluck('card_id')->unique())->toHaveCount(1)
        ->and($wemby->card->number)->toBe('136')
        ->and($wemby->card->cardSet->season)->toBe('2023-24')
        ->and($wemby->card->cardSet->year)->toBe(2023)
        ->and($wemby->card->is_autograph)->toBeFalse()
        ->and($wemby->parallel->name)->toBe('Silver')
        ->and($wemby->parallel->print_run)->toBe(99)
        ->and($wemby->serial_number)->toBe(12)
        ->and($wemby->is_graded)->toBeFalse()
        ->and($wemby->raw_condition)->toBe(RawCondition::NearMintMint)
        ->and($wemby->purchase_price)->toBe(2500)
        ->and($wemby->storage_location_id)->toBe($griffey->storage_location_id);

    expect(StorageLocation::query()->count())->toBe(2)
        ->and(CardSet::query()->count())->toBe(2)
        ->and(CollectionItem::query()->count())->toBe(3);
});

test('a retried import resumes from the committed offset and never duplicates rows', function () {
    $user = User::factory()->create();
    $csv = "Year,Set,Number,Player\n2001,Base,1,First\n2001,Base,2,Second\n2001,Base,3,Third\n";
    $import = startedImport($user, $csv, ['processed_rows' => 2, 'imported_rows' => 2]);

    ProcessImport::dispatchSync($import);
    ProcessImport::dispatchSync($import);

    expect(CollectionItem::query()->with('card')->get()->pluck('card.name')->all())->toBe(['Third'])
        ->and($import->refresh()->processed_rows)->toBe(3)
        ->and($import->imported_rows)->toBe(3)
        ->and($import->status)->toBe(ImportStatus::Completed);
});

test('the status page shows progress and results', function () {
    $user = User::factory()->create();
    $import = startedImport($user);
    ProcessImport::dispatchSync($import);

    $this->actingAs($user)
        ->get(route('imports.show', $import))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('imports/show')
            ->where('import.status', 'completed')
            ->where('import.imported_rows', 2)
            ->where('import.failed_rows', 4)
            ->where('import.tag_items_count', 3)
            ->has('import.failures', 4));
});

test('deleting an import’s cards removes only cards carrying its tag', function () {
    $user = User::factory()->create();
    $import = startedImport($user);
    ProcessImport::dispatchSync($import);
    $kept = CollectionItem::factory()->for($user)->create();

    $this->actingAs($user)
        ->delete(route('imports.cards.destroy', $import))
        ->assertRedirect(route('imports.show', $import));

    expect(CollectionItem::query()->pluck('id')->all())->toBe([$kept->id])
        ->and(Tag::query()->where('name', 'Import 2026-10-29')->exists())->toBeFalse();
});

test('a teammate can view and delete an import someone else started', function () {
    $import = Import::factory()->create();
    $teammate = User::factory()->create();

    $this->actingAs($teammate)->get(route('imports.show', $import))->assertOk();
    $this->actingAs($teammate)->delete(route('imports.destroy', $import))->assertSessionHasNoErrors();

    expect($import->fresh())->toBeNull();
});

test('guests cannot reach the importer', function () {
    $this->get(route('imports.index'))->assertRedirect(route('login'));
});
