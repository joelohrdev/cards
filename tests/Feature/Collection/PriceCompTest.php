<?php

use App\Enums\Grader;
use App\Models\CollectionItem;
use App\Models\PriceComp;
use App\Models\User;

test('a comp can be recorded and applied as the card’s value', function () {
    $user = User::factory()->create();
    $item = CollectionItem::factory()->for($user)->create(['estimated_value' => null]);

    $this->actingAs($user)
        ->post(route('collection.comps.store', $item), ['price' => '$1,250.00', 'sold_on' => now()->toDateString(), 'apply_to_item' => true])
        ->assertSessionHasNoErrors();

    expect(PriceComp::query()->sole()->price)->toBe(125000)
        ->and($item->refresh()->estimated_value)->toBe(125000);
});

test('applying comps averages only recent comps matching the copy’s parallel and grade', function () {
    $user = User::factory()->create();
    $item = CollectionItem::factory()->for($user)->graded(Grader::Psa, '10')->create();
    $matching = ['user_id' => $user->id, 'card_id' => $item->card_id, 'grader' => Grader::Psa, 'grade' => '10'];

    PriceComp::factory()->create([...$matching, 'price' => 10000, 'sold_on' => now()->subDays(5)]);
    PriceComp::factory()->create([...$matching, 'price' => 20000, 'sold_on' => now()->subDays(10)]);
    PriceComp::factory()->create([...$matching, 'price' => 90000, 'sold_on' => now()->subYear()]);
    PriceComp::factory()->create([...$matching, 'grade' => '9', 'price' => 1000, 'sold_on' => now()]);

    $this->actingAs($user)->post(route('collection.comps.apply', $item))->assertSessionHasNoErrors();

    expect($item->refresh()->estimated_value)->toBe(15000);
});

test('applying comps with no matches explains why', function () {
    $user = User::factory()->create();
    $item = CollectionItem::factory()->for($user)->create();

    $this->actingAs($user)
        ->post(route('collection.comps.apply', $item))
        ->assertSessionHasErrors(['comps' => 'No comps from the last 6 months match this card’s parallel and grade.']);
});

test('a teammate can delete a comp someone else recorded', function () {
    $comp = PriceComp::factory()->create();

    $this->actingAs(User::factory()->create())->delete(route('comps.destroy', $comp))->assertSessionHasNoErrors();

    expect($comp->fresh())->toBeNull();
});
