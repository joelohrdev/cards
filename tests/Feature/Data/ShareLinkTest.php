<?php

use App\Enums\ItemStatus;
use App\Models\Card;
use App\Models\CollectionItem;
use App\Models\ShareLink;
use App\Models\Tag;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('a share link can be created, limited to a tag', function () {
    $user = User::factory()->create();
    $tag = Tag::factory()->for($user)->create();

    $this->actingAs($user)
        ->post(route('share-links.store'), [
            'title' => 'Griffey PC',
            'description' => 'My favourite player.',
            'tag_id' => $tag->id,
            'show_values' => '1',
            'expires_at' => now()->addWeek()->toDateString(),
        ])
        ->assertRedirect(route('share-links.index'));

    $link = ShareLink::query()->sole();

    expect($link->user_id)->toBe($user->id)
        ->and($link->title)->toBe('Griffey PC')
        ->and($link->tag_id)->toBe($tag->id)
        ->and($link->show_values)->toBeTrue()
        ->and($link->expires_at->toDateString())->toBe(now()->addWeek()->toDateString())
        ->and(strlen($link->token))->toBe(40);

    $this->actingAs($user)
        ->get(route('share-links.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('share-links/index')
            ->has('links', 1)
            ->where('links.0.url', route('showcase', $link->token)));
});

test('a share link needs a tag that exists and an expiry that isn’t in the past', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('share-links.store'), [
            'title' => 'Mine',
            'tag_id' => 999999,
            'expires_at' => now()->subDay()->toDateString(),
        ])
        ->assertSessionHasErrors(['tag_id', 'expires_at']);

    expect(ShareLink::query()->count())->toBe(0);
});

test('a share link can be updated and deleted by its owner', function () {
    $user = User::factory()->create();
    $link = ShareLink::factory()->for($user)->create(['show_values' => true]);
    $token = $link->token;

    $this->actingAs($user)
        ->patch(route('share-links.update', $link), ['title' => 'Renamed'])
        ->assertRedirect(route('share-links.index'));

    expect($link->refresh()->title)->toBe('Renamed')
        ->and($link->show_values)->toBeFalse()
        ->and($link->token)->toBe($token);

    $this->actingAs($user)->delete(route('share-links.destroy', $link))->assertRedirect(route('share-links.index'));

    expect(ShareLink::query()->count())->toBe(0);
});

test('a teammate can change and delete a share link someone else made', function () {
    $link = ShareLink::factory()->create();
    $teammate = User::factory()->create();

    $this->actingAs($teammate)->patch(route('share-links.update', $link), ['title' => 'Renamed'])->assertRedirect(route('share-links.index'));

    expect($link->refresh()->title)->toBe('Renamed');

    $this->actingAs($teammate)->delete(route('share-links.destroy', $link))->assertRedirect(route('share-links.index'));

    expect($link->fresh())->toBeNull();
});

test('the public page shows only in-hand cards with the link’s tag, without private fields', function () {
    $owner = User::factory()->create();
    $tag = Tag::factory()->for($owner)->create();
    $shown = CollectionItem::factory()->for($owner)->graded()->create([
        'card_id' => Card::factory()->create(['name' => 'Ken Griffey Jr.']),
        'estimated_value' => 50000,
        'notes' => 'Secret note',
        'purchase_price' => 12345,
    ]);
    $shown->tags()->attach($tag);
    $untagged = CollectionItem::factory()->for($owner)->create();
    $sold = CollectionItem::factory()->for($owner)->status(ItemStatus::Sold)->create();
    $sold->tags()->attach($tag);
    $someoneElses = CollectionItem::factory()->create();
    $link = ShareLink::factory()->for($owner)->create(['tag_id' => $tag->id, 'show_values' => false]);

    $response = $this->get(route('showcase', $link->token));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('public/showcase')
            ->where('showcase.card_count', 1)
            ->where('showcase.total_value', null)
            ->has('items.data', 1)
            ->where('items.data.0.card.name', 'Ken Griffey Jr.')
            ->where('items.data.0.estimated_value', null)
            ->missing('items.data.0.id')
            ->missing('items.data.0.purchase_price')
            ->missing('items.data.0.notes')
            ->missing('items.data.0.cert_number')
            ->missing('items.data.0.location')
            ->missing('items.data.0.tags')
            ->where('sorts', fn ($sorts) => ! collect($sorts)->pluck('value')->contains('value_desc')));

    expect($response->getContent())
        ->not->toContain('Secret note')
        ->not->toContain($shown->cert_number)
        ->not->toContain('12345');
});

test('the public page shows values and value sorting when the owner allows it', function () {
    $owner = User::factory()->create();
    CollectionItem::factory()->for($owner)->create(['estimated_value' => 1000]);
    CollectionItem::factory()->for($owner)->create(['estimated_value' => 9000]);
    $link = ShareLink::factory()->for($owner)->create(['show_values' => true]);

    $this->get(route('showcase', $link->token))
        ->assertInertia(fn (Assert $page) => $page
            ->where('sort', 'value_desc')
            ->where('showcase.total_value', 10000)
            ->where('items.data.0.estimated_value', 9000)
            ->where('items.data.1.estimated_value', 1000));
});

test('the public page search only matches the shared cards', function () {
    $owner = User::factory()->create();
    CollectionItem::factory()->for($owner)->create(['card_id' => Card::factory()->create(['name' => 'Ken Griffey Jr.'])]);
    CollectionItem::factory()->for($owner)->create(['card_id' => Card::factory()->create(['name' => 'Mike Trout']), 'notes' => 'griffey lookalike']);
    $link = ShareLink::factory()->for($owner)->create();

    $this->get(route('showcase', ['token' => $link->token, 'q' => 'griffey']))
        ->assertInertia(fn (Assert $page) => $page->has('items.data', 1)->where('items.data.0.card.name', 'Ken Griffey Jr.'));
});

test('expired and unknown share links are not found', function () {
    $expired = ShareLink::factory()->create(['expires_at' => now()->subMinute()]);

    $this->get(route('showcase', $expired->token))->assertNotFound();
    $this->get(route('showcase', str_repeat('a', 40)))->assertNotFound();
});
