<?php

use App\Models\Card;
use App\Models\CardSet;
use App\Models\Parallel;
use App\Models\User;

test('quick add creates items for typed numbers, reusing checklist cards', function () {
    $user = User::factory()->create();
    $set = CardSet::factory()->create();
    $existing = Card::factory()->for($set)->create(['number' => '10', 'name' => 'Tony Gwynn']);
    $parallel = Parallel::factory()->for($set)->create();

    $this->actingAs($user)
        ->post(route('collection.quick-add.store'), [
            'card_set_id' => $set->id,
            'tags' => ['Box 1 sort'],
            'rows' => [
                ['number' => '10', 'quantity' => 2, 'raw_condition' => 'ex'],
                ['number' => '11', 'name' => 'Mark McGwire', 'parallel_id' => $parallel->id, 'grader' => 'PSA', 'grade' => '9', 'purchase_price' => '4.50'],
            ],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('collection.quick-add'));

    expect($user->collectionItems()->where('card_id', $existing->id)->count())->toBe(2)
        ->and($existing->refresh()->name)->toBe('Tony Gwynn');

    $graded = $user->collectionItems()->whereHas('card', fn ($card) => $card->where('number', '11'))->sole();

    expect($graded->card->name)->toBe('Mark McGwire')
        ->and($graded->parallel_id)->toBe($parallel->id)
        ->and($graded->is_graded)->toBeTrue()
        ->and($graded->purchase_price)->toBe(450)
        ->and($graded->tags->pluck('name')->all())->toBe(['Box 1 sort']);
});

test('quick add rejects a parallel from another set and rows without numbers', function () {
    $set = CardSet::factory()->create();

    $this->actingAs(User::factory()->create())
        ->post(route('collection.quick-add.store'), [
            'card_set_id' => $set->id,
            'rows' => [
                ['number' => '', 'name' => 'Nobody'],
                ['number' => '2', 'parallel_id' => Parallel::factory()->create()->id],
            ],
        ])
        ->assertSessionHasErrors(['rows.0.number' => 'Every row needs a card number.', 'rows.1.parallel_id']);
});
