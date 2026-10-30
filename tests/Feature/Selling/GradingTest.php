<?php

use App\Enums\Grader;
use App\Enums\GradingStatus;
use App\Enums\ItemStatus;
use App\Enums\RawCondition;
use App\Models\CollectionItem;
use App\Models\GradingSubmission;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * A submission owned by $user holding the given items, each with a fee in cents.
 *
 * @param  list<CollectionItem>  $items
 */
function submissionWith(User $user, array $items, int $fee = 2500, array $attributes = []): GradingSubmission
{
    $submission = GradingSubmission::factory()->for($user)->create($attributes);

    foreach ($items as $item) {
        $submission->collectionItems()->attach($item->id, ['fee' => $fee, 'declared_value' => 10000]);
    }

    return $submission;
}

test('guests are redirected to the login page', function () {
    $this->get(route('grading.index'))->assertRedirect(route('login'));
});

test('the grading page lists every submission with fee and turnaround stats', function () {
    $user = User::factory()->create();
    $item = CollectionItem::factory()->for($user)->create();
    submissionWith($user, [$item], fee: 2000, attributes: ['shipping_cost' => 1500]);
    GradingSubmission::factory()->for($user)->create([
        'status' => GradingStatus::Completed,
        'submitted_on' => '2026-01-01',
        'returned_on' => '2026-01-31',
    ]);
    GradingSubmission::factory()->create();

    $this->actingAs($user)
        ->get(route('grading.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('grading/index')
            ->has('open', 2)
            ->has('completed', 1)
            ->where('stats.open_count', 2)
            ->where('stats.completed_count', 1)
            ->where('stats.total_fees', 3500)
            ->where('stats.average_turnaround_days', 30));
});

test('a submission can be started with raw cards in it', function () {
    $user = User::factory()->create();
    $item = CollectionItem::factory()->for($user)->create(['estimated_value' => 5000]);

    $this->actingAs($user)
        ->post(route('grading.store'), [
            'grader' => 'PSA',
            'service_level' => 'Value',
            'shipping_cost' => '$25.50',
            'collection_item_ids' => [$item->id],
        ])
        ->assertSessionHasNoErrors();

    $submission = GradingSubmission::query()->sole();

    expect($submission->user_id)->toBe($user->id)
        ->and($submission->status)->toBe(GradingStatus::Preparing)
        ->and($submission->shipping_cost)->toBe(2550)
        ->and($submission->collectionItems()->sole()->getRelation('pivot')->declared_value)->toBe(5000)
        ->and($item->fresh()->status)->toBe(ItemStatus::Owned);
});

test('a teammate can view a submission and add cards someone else added to the collection', function () {
    $submission = GradingSubmission::factory()->create();
    $item = CollectionItem::factory()->create();
    $teammate = User::factory()->create();

    $this->actingAs($teammate)->get(route('grading.show', $submission))->assertOk();
    $this->actingAs($teammate)
        ->post(route('grading.items.store', $submission), ['collection_item_ids' => [$item->id]])
        ->assertSessionHasNoErrors();

    expect($submission->collectionItems()->sole()->id)->toBe($item->id);
});

test('the owner can view a submission with its cards', function () {
    $user = User::factory()->create();
    $item = CollectionItem::factory()->for($user)->create();
    $submission = submissionWith($user, [$item]);

    $this->actingAs($user)
        ->get(route('grading.show', $submission))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('grading/show')
            ->where('submission.id', $submission->id)
            ->has('items', 1)
            ->where('items.0.item.id', $item->id)
            ->where('totals.fees', 2500));
});

test('sending a submission puts its cards at grading and moving it back returns them', function () {
    $user = User::factory()->create();
    $item = CollectionItem::factory()->for($user)->create();
    $submission = submissionWith($user, [$item]);

    $this->actingAs($user)
        ->patch(route('grading.update', $submission), ['grader' => 'PSA', 'status' => 'submitted'])
        ->assertSessionHasNoErrors();

    expect($item->fresh()->status)->toBe(ItemStatus::AtGrading)
        ->and($submission->fresh()->submitted_on)->not->toBeNull();

    $this->actingAs($user)
        ->patch(route('grading.update', $submission), ['grader' => 'PSA', 'status' => 'preparing']);

    expect($item->fresh()->status)->toBe(ItemStatus::Owned);
});

test('a submission can’t be completed through a status change', function () {
    $user = User::factory()->create();
    $submission = GradingSubmission::factory()->for($user)->create();

    $this->actingAs($user)
        ->patch(route('grading.update', $submission), ['grader' => 'PSA', 'status' => 'completed'])
        ->assertSessionHasErrors('status');
});

test('cards added to a submission that was already sent go straight to at grading', function () {
    $user = User::factory()->create();
    $item = CollectionItem::factory()->for($user)->create();
    $submission = GradingSubmission::factory()->for($user)->create(['status' => GradingStatus::Grading]);

    $this->actingAs($user)
        ->post(route('grading.items.store', $submission), ['collection_item_ids' => [$item->id]])
        ->assertSessionHasNoErrors();

    expect($submission->collectionItems()->count())->toBe(1)
        ->and($item->fresh()->status)->toBe(ItemStatus::AtGrading);
});

test('only raw, in-hand cards can be added', function (Closure $makeItem) {
    $user = User::factory()->create();
    $submission = GradingSubmission::factory()->for($user)->create();
    $item = $makeItem($user);

    $this->actingAs($user)
        ->post(route('grading.items.store', $submission), ['collection_item_ids' => [$item->id]])
        ->assertSessionHasErrors('collection_item_ids.0');

    expect($submission->collectionItems()->count())->toBe(0);
})->with([
    'a graded card' => [fn (User $user) => CollectionItem::factory()->for($user)->graded()->create()],
    'a sold card' => [fn (User $user) => CollectionItem::factory()->for($user)->status(ItemStatus::Sold)->create()],
    'a card in another open submission' => [function (User $user) {
        $item = CollectionItem::factory()->for($user)->create();
        submissionWith($user, [$item]);

        return $item;
    }],
]);

test('a card’s declared value and fee can be changed', function () {
    $user = User::factory()->create();
    $item = CollectionItem::factory()->for($user)->create();
    $submission = submissionWith($user, [$item]);

    $this->actingAs($user)
        ->patch(route('grading.items.update', [$submission, $item]), ['declared_value' => '$1,200', 'fee' => '74.99'])
        ->assertSessionHasNoErrors();

    $pivot = $submission->collectionItems()->sole()->getRelation('pivot');

    expect($pivot->declared_value)->toBe(120000)
        ->and($pivot->fee)->toBe(7499);
});

test('removing a card from a sent submission returns it to owned', function () {
    $user = User::factory()->create();
    $item = CollectionItem::factory()->for($user)->status(ItemStatus::AtGrading)->create();
    $submission = submissionWith($user, [$item], attributes: ['status' => GradingStatus::Submitted]);

    $this->actingAs($user)
        ->delete(route('grading.items.destroy', [$submission, $item]))
        ->assertSessionHasNoErrors();

    expect($submission->collectionItems()->count())->toBe(0)
        ->and($item->fresh()->status)->toBe(ItemStatus::Owned);
});

test('deleting a sent submission returns its cards to owned', function () {
    $user = User::factory()->create();
    $item = CollectionItem::factory()->for($user)->status(ItemStatus::AtGrading)->create();
    $submission = submissionWith($user, [$item], attributes: ['status' => GradingStatus::Shipped]);

    $this->actingAs($user)
        ->delete(route('grading.destroy', $submission))
        ->assertRedirect(route('grading.index'));

    expect(GradingSubmission::query()->count())->toBe(0)
        ->and($item->fresh()->status)->toBe(ItemStatus::Owned);
});

test('recording results grades the cards and adds fees and a share of shipping to their cost', function () {
    $user = User::factory()->create();
    $first = CollectionItem::factory()->for($user)->status(ItemStatus::AtGrading)->create(['purchase_price' => 10000, 'raw_condition' => RawCondition::NearMint]);
    $second = CollectionItem::factory()->for($user)->status(ItemStatus::AtGrading)->create(['purchase_price' => null]);
    $submission = submissionWith($user, [$first, $second], fee: 2500, attributes: [
        'grader' => Grader::Bgs,
        'status' => GradingStatus::Shipped,
        'shipping_cost' => 1001,
    ]);

    $this->actingAs($user)
        ->post(route('grading.results.store', $submission), [
            'results' => [
                $first->id => ['grade' => '9.5', 'cert_number' => '0012345678'],
                $second->id => ['grade' => '', 'cert_number' => ''],
            ],
            'add_costs_to_basis' => true,
            'returned_on' => '2026-10-01',
        ])
        ->assertSessionHasNoErrors();

    $first->refresh();
    $second->refresh();
    $submission->refresh();

    expect($submission->status)->toBe(GradingStatus::Completed)
        ->and($submission->returned_on->toDateString())->toBe('2026-10-01')
        ->and($first->is_graded)->toBeTrue()
        ->and($first->grader)->toBe(Grader::Bgs)
        ->and($first->grade)->toBe('9.5')
        ->and($first->cert_number)->toBe('0012345678')
        ->and($first->raw_condition)->toBeNull()
        ->and($first->status)->toBe(ItemStatus::Owned)
        ->and($first->purchase_price)->toBe(10000 + 2500 + 501)
        ->and($second->is_graded)->toBeFalse()
        ->and($second->status)->toBe(ItemStatus::Owned)
        ->and($second->purchase_price)->toBe(2500 + 500)
        ->and($submission->collectionItems()->whereKey($first->id)->sole()->getRelation('pivot')->resulting_grade)->toBe('9.5');
});

test('recording results can leave the cards’ cost basis alone', function () {
    $user = User::factory()->create();
    $item = CollectionItem::factory()->for($user)->status(ItemStatus::AtGrading)->create(['purchase_price' => 10000]);
    $submission = submissionWith($user, [$item], attributes: ['status' => GradingStatus::Shipped, 'shipping_cost' => 1000]);

    $this->actingAs($user)
        ->post(route('grading.results.store', $submission), [
            'results' => [$item->id => ['grade' => '10', 'cert_number' => '123']],
            'add_costs_to_basis' => false,
        ])
        ->assertSessionHasNoErrors();

    expect($item->fresh()->purchase_price)->toBe(10000)
        ->and($item->fresh()->grade)->toBe('10');
});

test('results can’t be recorded twice', function () {
    $user = User::factory()->create();
    $item = CollectionItem::factory()->for($user)->create();
    $submission = submissionWith($user, [$item], attributes: ['status' => GradingStatus::Completed]);

    $this->actingAs($user)
        ->post(route('grading.results.store', $submission), ['results' => [$item->id => ['grade' => '10']]])
        ->assertSessionHasErrors('results');
});

test('the add-to-grading picker only offers raw cards that aren’t already submitted', function () {
    $user = User::factory()->create();
    $eligible = CollectionItem::factory()->for($user)->create();
    $submitted = CollectionItem::factory()->for($user)->create();
    submissionWith($user, [$submitted]);
    CollectionItem::factory()->for($user)->graded()->create();
    $teammates = CollectionItem::factory()->create();

    $this->actingAs($user)
        ->getJson(route('grading.candidates'))
        ->assertOk()
        ->assertJsonCount(2, 'items')
        ->assertJsonFragment(['id' => $eligible->id])
        ->assertJsonFragment(['id' => $teammates->id]);
});

test('only preparing submissions are offered for new cards', function () {
    $user = User::factory()->create();
    $preparing = GradingSubmission::factory()->for($user)->create();
    GradingSubmission::factory()->for($user)->create(['status' => GradingStatus::Submitted]);
    $teammates = GradingSubmission::factory()->create();

    $this->actingAs($user)
        ->getJson(route('grading.preparing'))
        ->assertOk()
        ->assertJsonCount(2, 'submissions')
        ->assertJsonFragment(['id' => $preparing->id])
        ->assertJsonFragment(['id' => $teammates->id]);
});
