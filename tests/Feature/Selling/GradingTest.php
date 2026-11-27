<?php

use App\Enums\Grader;
use App\Enums\GradingStatus;
use App\Enums\ItemStatus;
use App\Enums\RawCondition;
use App\Models\Card;
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

test('each step of a submission can be marked with one tap and fills in its date', function () {
    $this->travelTo('2026-11-02');
    $user = User::factory()->create();
    $item = CollectionItem::factory()->for($user)->create();
    $submission = submissionWith($user, [$item]);

    $this->actingAs($user)->post(route('grading.progress.store', $submission), ['status' => 'submitted'])->assertSessionHasNoErrors();
    expect($item->fresh()->status)->toBe(ItemStatus::AtGrading);

    $this->travelTo('2026-11-06');
    $this->actingAs($user)->post(route('grading.progress.store', $submission), ['status' => 'received'])->assertSessionHasNoErrors();
    $this->actingAs($user)->post(route('grading.progress.store', $submission), ['status' => 'grading'])->assertSessionHasNoErrors();

    $this->travelTo('2026-11-20');
    $this->actingAs($user)->post(route('grading.progress.store', $submission), ['status' => 'shipped'])->assertSessionHasNoErrors();

    $submission->refresh();
    expect($submission->status)->toBe(GradingStatus::Shipped)
        ->and($submission->submitted_on->toDateString())->toBe('2026-11-02')
        ->and($submission->received_on->toDateString())->toBe('2026-11-06')
        ->and($submission->shipped_back_on->toDateString())->toBe('2026-11-20');

    $this->actingAs($user)
        ->post(route('grading.progress.store', $submission), ['status' => 'completed'])
        ->assertSessionHasErrors('status');
});

test('marking the next step twice doesn’t skip a step', function () {
    $user = User::factory()->create();
    $submission = submissionWith($user, [CollectionItem::factory()->for($user)->create()], attributes: ['status' => GradingStatus::Submitted]);

    $this->actingAs($user)->post(route('grading.progress.store', $submission), ['status' => 'received']);
    $this->actingAs($user)
        ->post(route('grading.progress.store', $submission), ['status' => 'received'])
        ->assertSessionHasErrors('status');

    expect($submission->fresh()->status)->toBe(GradingStatus::Received);
});

test('a typed-in milestone date is kept when the status catches up', function () {
    $user = User::factory()->create();
    $submission = submissionWith($user, [CollectionItem::factory()->for($user)->create()], attributes: ['status' => GradingStatus::Submitted, 'submitted_on' => '2026-10-01']);

    $this->actingAs($user)
        ->patch(route('grading.update', $submission), ['grader' => 'PSA', 'status' => 'received', 'submitted_on' => '2026-10-01', 'received_on' => '2026-10-04'])
        ->assertSessionHasNoErrors();

    expect($submission->fresh()->received_on->toDateString())->toBe('2026-10-04');
});

test('shipments are saved with a link to the carrier’s tracking page', function () {
    $this->travelTo('2026-11-27');
    $user = User::factory()->create();
    $submission = submissionWith($user, [CollectionItem::factory()->for($user)->create()]);

    $this->actingAs($user)
        ->patch(route('grading.update', $submission), [
            'grader' => 'PSA',
            'status' => 'shipped',
            'submitted_on' => '2026-11-01',
            'outbound_carrier' => 'usps',
            'outbound_tracking' => '9400 1000',
            'return_carrier' => 'other',
            'return_tracking' => 'ABC123',
        ])
        ->assertSessionHasNoErrors();

    $this->actingAs($user)
        ->get(route('grading.show', $submission))
        ->assertInertia(fn (Assert $page) => $page
            ->where('submission.outbound.carrier_label', 'USPS')
            ->where('submission.outbound.tracking_url', 'https://tools.usps.com/go/TrackConfirmAction?tLabels=9400%201000')
            ->where('submission.return.tracking', 'ABC123')
            ->where('submission.return.tracking_url', null)
            ->where('submission.shipped_back_on', '2026-11-27')
            ->where('submission.days_out', 26)
            ->where('submission.next_action', null));
});

test('a card’s page shows where its grading submission is, with tracking', function () {
    $this->travelTo('2026-11-27');
    $user = User::factory()->create();
    $item = CollectionItem::factory()->for($user)->create();
    submissionWith($user, [$item], attributes: [
        'status' => GradingStatus::Received,
        'submitted_on' => '2026-11-17',
        'received_on' => '2026-11-20',
        'outbound_carrier' => 'ups',
        'outbound_tracking' => '1Z999',
        'reference' => '12345',
    ]);

    $this->actingAs($user)
        ->get(route('collection.show', $item))
        ->assertInertia(fn (Assert $page) => $page
            ->component('collection/show')
            ->has('gradingSubmissions', 1)
            ->where('gradingSubmissions.0.status', 'received')
            ->where('gradingSubmissions.0.status_label', 'Received by grader')
            ->where('gradingSubmissions.0.is_completed', false)
            ->where('gradingSubmissions.0.reference', '12345')
            ->where('gradingSubmissions.0.received_on', '2026-11-20')
            ->where('gradingSubmissions.0.outbound.tracking_url', 'https://www.ups.com/track?tracknum=1Z999')
            ->where('gradingSubmissions.0.days_out', 10));
});

test('milestone dates can’t go backwards, and only filled-in dates are compared', function () {
    $user = User::factory()->create();
    $submission = GradingSubmission::factory()->for($user)->create();

    $this->actingAs($user)
        ->patch(route('grading.update', $submission), ['grader' => 'PSA', 'submitted_on' => '2026-10-10', 'received_on' => '2026-10-05'])
        ->assertSessionHasErrors('received_on');

    $this->actingAs($user)
        ->patch(route('grading.update', $submission), ['grader' => 'PSA', 'returned_on' => '2026-10-05'])
        ->assertSessionHasNoErrors();
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

test('a new card can be marked as going to send in a new submission', function () {
    $user = User::factory()->create();
    $card = Card::factory()->create();

    $this->actingAs($user)
        ->post(route('collection.store'), [
            'card_id' => $card->id,
            'quantity' => 2,
            'grading' => ['plan' => 'going', 'grader' => 'SGC', 'service_level' => 'Bulk'],
        ])
        ->assertSessionHasNoErrors();

    $submission = GradingSubmission::query()->sole();
    expect($submission->grader)->toBe(Grader::Sgc)
        ->and($submission->status)->toBe(GradingStatus::Preparing)
        ->and($submission->status->label())->toBe('Going to send')
        ->and($submission->collectionItems()->count())->toBe(2)
        ->and($user->collectionItems()->pluck('status')->unique()->all())->toBe([ItemStatus::Owned]);
});

test('a new card can be added as already sent, or into an open submission', function () {
    $this->travelTo('2026-11-27');
    $user = User::factory()->create();
    $card = Card::factory()->create();

    $this->actingAs($user)
        ->post(route('collection.store'), [
            'card_id' => $card->id,
            'grading' => ['plan' => 'sent', 'grader' => 'PSA', 'status' => 'received'],
        ])
        ->assertSessionHasNoErrors();

    $sent = GradingSubmission::query()->sole();
    expect($sent->status)->toBe(GradingStatus::Received)
        ->and($sent->submitted_on->toDateString())->toBe('2026-11-27')
        ->and($sent->collectionItems()->sole()->status)->toBe(ItemStatus::AtGrading);

    $this->actingAs($user)
        ->post(route('collection.store'), ['card_id' => $card->id, 'grading' => ['plan' => 'sent', 'submission_id' => $sent->id]])
        ->assertSessionHasNoErrors();

    expect($sent->collectionItems()->count())->toBe(2)
        ->and(GradingSubmission::query()->count())->toBe(1);
});

test('a new card can’t join a submission that doesn’t match the plan, or be graded already', function () {
    $user = User::factory()->create();
    $card = Card::factory()->create();
    $goingToSend = GradingSubmission::factory()->for($user)->create();

    $this->actingAs($user)
        ->post(route('collection.store'), ['card_id' => $card->id, 'grading' => ['plan' => 'sent', 'submission_id' => $goingToSend->id]])
        ->assertSessionHasErrors('grading.submission_id');

    $this->actingAs($user)
        ->post(route('collection.store'), ['card_id' => $card->id, 'is_graded' => true, 'grader' => 'PSA', 'grade' => '9', 'grading' => ['plan' => 'going', 'grader' => 'PSA']])
        ->assertSessionHasErrors('grading');

    $this->actingAs($user)
        ->post(route('collection.store'), ['card_id' => $card->id, 'grading' => ['plan' => 'going']])
        ->assertSessionHasErrors('grading.grader');

    expect(CollectionItem::query()->count())->toBe(0);
});

test('the add-card page offers open submissions to join', function () {
    $user = User::factory()->create();
    GradingSubmission::factory()->for($user)->create();
    GradingSubmission::factory()->for($user)->create(['status' => GradingStatus::Grading]);
    GradingSubmission::factory()->for($user)->create(['status' => GradingStatus::Completed]);

    $this->actingAs($user)
        ->get(route('collection.create'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('gradingSubmissions', 2)
            ->where('gradingSubmissions.1.status_label', 'Going to send')
            ->where('gradingSubmissions.1.is_sent', false)
            ->has('gradingStatuses', 4));
});

test('the overview lists cards awaiting grading, going to send first', function () {
    $user = User::factory()->create();
    $out = CollectionItem::factory()->for($user)->create();
    $planned = CollectionItem::factory()->for($user)->create();
    $done = CollectionItem::factory()->for($user)->create();
    submissionWith($user, [$out], attributes: ['status' => GradingStatus::Grading, 'submitted_on' => now()->subDays(12)]);
    submissionWith($user, [$planned]);
    submissionWith($user, [$done], attributes: ['status' => GradingStatus::Completed]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('awaitingGrading.going_to_send', 1)
            ->where('awaitingGrading.at_grader', 1)
            ->has('awaitingGrading.items', 2)
            ->where('awaitingGrading.items.0.item.id', $planned->id)
            ->where('awaitingGrading.items.0.submission.status_label', 'Going to send')
            ->where('awaitingGrading.items.1.item.id', $out->id)
            ->where('awaitingGrading.items.1.submission.days_out', 12));
});
