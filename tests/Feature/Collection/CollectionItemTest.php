<?php

use App\Enums\Grader;
use App\Enums\ItemStatus;
use App\Enums\Sport;
use App\Models\Card;
use App\Models\CardSet;
use App\Models\CollectionItem;
use App\Models\Parallel;
use App\Models\StorageLocation;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $this->get(route('collection.index'))->assertRedirect(route('login'));
});

test('the collection lists every card, whoever added it', function () {
    $user = User::factory()->create();
    CollectionItem::factory()->for($user)->create();
    CollectionItem::factory()->create();

    $this->actingAs($user)
        ->get(route('collection.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('collection/index')
            ->has('items.data', 2)
            ->where('totals.count', 2));
});

test('the collection hides sold cards unless asked for them', function () {
    $user = User::factory()->create();
    CollectionItem::factory()->for($user)->create();
    CollectionItem::factory()->for($user)->status(ItemStatus::Sold)->create();

    $this->actingAs($user)
        ->get(route('collection.index'))
        ->assertInertia(fn (Assert $page) => $page->has('items.data', 1));

    $this->actingAs($user)
        ->get(route('collection.index', ['status' => ['all']]))
        ->assertInertia(fn (Assert $page) => $page->has('items.data', 2));
});

test('a card can be added with a brand new set, card and parallel', function () {
    $user = User::factory()->create();
    $location = StorageLocation::factory()->for($user)->create();

    $this->actingAs($user)
        ->post(route('collection.store'), [
            'new_set' => ['sport' => 'baseball', 'year' => 1989, 'manufacturer' => 'Upper Deck', 'name' => 'Base'],
            'card' => ['number' => '1', 'name' => 'Ken Griffey Jr.', 'team' => 'Seattle Mariners', 'is_rookie' => true],
            'parallel_name' => 'Gold',
            'parallel_print_run' => 50,
            'serial_number' => 12,
            'is_graded' => true,
            'grader' => 'PSA',
            'grade' => '9',
            'purchase_price' => '120.50',
            'estimated_value' => '$1,200',
            'storage_location_id' => $location->id,
            'tags' => ['PC', 'Mariners'],
        ])
        ->assertSessionHasNoErrors();

    $item = CollectionItem::query()->sole();
    $set = CardSet::query()->sole();

    expect($set->year)->toBe(1989)
        ->and($set->sport)->toBe(Sport::Baseball)
        ->and($set->manufacturer->name)->toBe('Upper Deck')
        ->and($item->user_id)->toBe($user->id)
        ->and($item->card->name)->toBe('Ken Griffey Jr.')
        ->and($item->card->is_rookie)->toBeTrue()
        ->and($item->card->team->name)->toBe('Seattle Mariners')
        ->and($item->card->players->pluck('name')->all())->toBe(['Ken Griffey Jr.'])
        ->and($item->parallel->name)->toBe('Gold')
        ->and($item->parallel->print_run)->toBe(50)
        ->and($item->grader)->toBe(Grader::Psa)
        ->and($item->purchase_price)->toBe(12050)
        ->and($item->estimated_value)->toBe(120000)
        ->and($item->storage_location_id)->toBe($location->id)
        ->and($item->tags->pluck('name')->sort()->values()->all())->toBe(['Mariners', 'PC']);
});

test('several copies of an existing catalog card can be added at once', function () {
    $user = User::factory()->create();
    $card = Card::factory()->create();

    $this->actingAs($user)
        ->post(route('collection.store'), ['card_id' => $card->id, 'quantity' => 3, 'raw_condition' => 'ex'])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($user->collectionItems()->where('card_id', $card->id)->count())->toBe(3);
});

test('adding a card requires choosing or describing one', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('collection.store'), [])
        ->assertSessionHasErrors(['card' => 'Choose a card from the catalog or enter a new one.']);
});

test('a parallel from a different set is rejected', function () {
    $card = Card::factory()->create();
    $otherParallel = Parallel::factory()->create();

    $this->actingAs(User::factory()->create())
        ->post(route('collection.store'), ['card_id' => $card->id, 'parallel_id' => $otherParallel->id])
        ->assertSessionHasErrors(['parallel_id' => 'That parallel belongs to a different set.']);
});

test('a card can be stored in a location a teammate created', function () {
    $card = Card::factory()->create();
    $location = StorageLocation::factory()->create();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('collection.store'), ['card_id' => $card->id, 'storage_location_id' => $location->id])
        ->assertSessionHasNoErrors();

    expect($user->collectionItems()->sole()->storage_location_id)->toBe($location->id);
});

test('a storage location that doesn’t exist is rejected', function () {
    $card = Card::factory()->create();

    $this->actingAs(User::factory()->create())
        ->post(route('collection.store'), ['card_id' => $card->id, 'storage_location_id' => 999999])
        ->assertSessionHasErrors('storage_location_id');
});

test('a card can be viewed, updated and deleted by its owner', function () {
    $user = User::factory()->create();
    $item = CollectionItem::factory()->for($user)->create();

    $this->actingAs($user)->get(route('collection.show', $item))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('collection/show')->where('item.id', $item->id));

    $this->actingAs($user)->get(route('collection.edit', $item))->assertOk();

    $this->actingAs($user)
        ->put(route('collection.update', $item), [
            'card_id' => $item->card_id,
            'status' => 'listed',
            'raw_condition' => 'vg',
            'estimated_value' => '15',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('collection.show', $item));

    expect($item->refresh()->status)->toBe(ItemStatus::Listed)
        ->and($item->estimated_value)->toBe(1500);

    $this->actingAs($user)->delete(route('collection.destroy', $item))->assertRedirect(route('collection.index'));

    expect($item->fresh())->toBeNull();
});

test('a teammate can view, update and delete a card someone else added', function () {
    $item = CollectionItem::factory()->create();
    $teammate = User::factory()->create();

    $this->actingAs($teammate)->get(route('collection.show', $item))->assertOk();
    $this->actingAs($teammate)
        ->put(route('collection.update', $item), ['card_id' => $item->card_id, 'status' => 'listed'])
        ->assertSessionHasNoErrors();

    expect($item->refresh()->status)->toBe(ItemStatus::Listed);

    $this->actingAs($teammate)->delete(route('collection.destroy', $item))->assertRedirect(route('collection.index'));

    expect($item->fresh())->toBeNull();
});

test('the add form is shown, optionally pre-filled with a catalog card', function () {
    $card = Card::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('collection.create', ['card' => $card->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('collection/create')->where('prefillCard.id', $card->id));
});

test('a photo sent with a new card becomes its front image', function () {
    Storage::fake('public');
    $user = User::factory()->create();
    $card = Card::factory()->create();

    $this->actingAs($user)
        ->post(route('collection.store'), ['card_id' => $card->id, 'photo' => UploadedFile::fake()->image('front.jpg')])
        ->assertSessionHasNoErrors();

    $image = $user->collectionItems()->sole()->images()->sole();

    expect($image->side->value)->toBe('front');
    Storage::disk('public')->assertExists($image->path);
});

test('photos can be uploaded, reordered and deleted on a card', function () {
    Storage::fake('public');
    $user = User::factory()->create();
    $item = CollectionItem::factory()->for($user)->create();

    $this->actingAs($user)
        ->post(route('collection.images.store', $item), ['images' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')]])
        ->assertSessionHasNoErrors();

    [$front, $back] = $item->images()->get()->all();
    expect([$front->side->value, $back->side->value])->toBe(['front', 'back']);

    $this->actingAs($user)->patch(route('collection.images.update', [$item, $back]))->assertSessionHasNoErrors();
    expect($item->images()->first()->id)->toBe($back->id);

    $this->actingAs($user)->delete(route('collection.images.destroy', [$item, $front]))->assertSessionHasNoErrors();
    Storage::disk('public')->assertMissing($front->path);
});

test('a back photo sent with a new card becomes its back image', function () {
    Storage::fake('public');
    $user = User::factory()->create();
    $card = Card::factory()->create();

    $this->actingAs($user)
        ->post(route('collection.store'), [
            'card_id' => $card->id,
            'photo' => UploadedFile::fake()->image('front.jpg'),
            'back_photo' => UploadedFile::fake()->image('back.jpg'),
        ])
        ->assertSessionHasNoErrors();

    $images = $user->collectionItems()->sole()->images()->orderBy('position')->get();

    expect($images->pluck('side.value')->all())->toBe(['front', 'back']);
    Storage::disk('public')->assertExists($images->last()->path);
});

test('removing someone’s account keeps the cards they added', function () {
    $user = User::factory()->create();
    $item = CollectionItem::factory()->for($user)->create();

    $user->delete();

    expect($item->fresh())->not->toBeNull()
        ->and($item->fresh()->user_id)->toBeNull();
});
