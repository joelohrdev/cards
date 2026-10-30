<?php

use App\Models\Card;
use App\Models\CardSet;
use App\Models\Manufacturer;
use App\Models\Player;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['services.anthropic.key' => 'sk-test', 'services.anthropic.model' => 'claude-sonnet-5-5']);
});

/**
 * A Messages API response whose text block is the given JSON.
 */
function claudeReply(string $text, string $stopReason = 'end_turn'): array
{
    return [
        'id' => 'msg_test',
        'type' => 'message',
        'role' => 'assistant',
        'model' => 'claude-sonnet-5-5',
        'content' => [['type' => 'text', 'text' => $text]],
        'stop_reason' => $stopReason,
    ];
}

test('a photo is identified, normalized, and matched to catalog cards', function () {
    Http::preventStrayRequests();
    Http::fake([
        'api.anthropic.com/v1/messages' => Http::response(claudeReply(json_encode([
            'sport' => 'basketball',
            'year' => 1986,
            'manufacturer' => 'Fleer',
            'set_name' => 'Base',
            'number' => '#57',
            'name' => 'Michael Jordan',
            'team' => 'Chicago Bulls',
            'parallel' => null,
            'serial_number' => 120,
            'print_run' => 99,
            'is_rookie' => true,
            'is_autograph' => false,
            'is_memorabilia' => false,
            'is_graded' => true,
            'grader' => 'psa',
            'grade' => '8',
            'cert_number' => '12345678',
            'confidence' => 'high',
        ]))),
    ]);

    $set = CardSet::factory()->for(Manufacturer::factory()->state(['name' => 'Fleer']))->create(['year' => 1986, 'name' => 'Base']);
    $jordan = Card::factory()->for($set)->create(['number' => '57', 'name' => 'Michael Jordan']);
    $jordan->players()->attach(Player::factory()->create(['name' => 'Michael Jordan']));
    Card::factory()->for($set)->create(['number' => '58', 'name' => 'Someone Else']);

    $this->actingAs(User::factory()->create())
        ->post(route('identify.photo.store'), ['photo' => UploadedFile::fake()->image('card.jpg')], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('suggestion.year', 1986)
        ->assertJsonPath('suggestion.number', '57')
        ->assertJsonPath('suggestion.grader', 'PSA')
        ->assertJsonPath('suggestion.serial_number', null)
        ->assertJsonPath('suggestion.print_run', 99)
        ->assertJsonPath('suggestion.confidence', 'high')
        ->assertJsonCount(1, 'suggestion.matches')
        ->assertJsonPath('suggestion.matches.0.id', $jordan->id)
        ->assertJsonStructure(['suggestion' => ['matches' => [['id', 'number', 'name', 'set', 'parallels']]]]);

    Http::assertSent(fn (Request $request): bool => $request->hasHeader('x-api-key', 'sk-test')
        && $request['model'] === 'claude-sonnet-5-5'
        && $request['output_config']['format']['type'] === 'json_schema'
        && $request['messages'][0]['content'][0]['type'] === 'image');
});

test('malformed model output returns a retry message instead of a suggestion', function (string $text) {
    Http::preventStrayRequests();
    Http::fake(['api.anthropic.com/v1/messages' => Http::response(claudeReply($text))]);

    $this->actingAs(User::factory()->create())
        ->post(route('identify.photo.store'), ['photo' => UploadedFile::fake()->image('card.jpg')], ['Accept' => 'application/json'])
        ->assertStatus(502)
        ->assertJsonMissingPath('suggestion');
})->with([
    'not JSON' => ['It looks like a baseball card!'],
    'a JSON list' => ['[1, 2, 3]'],
]);

test('a refusal from the model returns a retry message', function () {
    Http::preventStrayRequests();
    Http::fake(['api.anthropic.com/v1/messages' => Http::response(claudeReply('', 'refusal'))]);

    $this->actingAs(User::factory()->create())
        ->post(route('identify.photo.store'), ['photo' => UploadedFile::fake()->image('card.jpg')], ['Accept' => 'application/json'])
        ->assertStatus(502);
});

test('identification explains when no API key is configured', function () {
    config(['services.anthropic.key' => null]);
    Http::preventStrayRequests();

    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson(route('identify.photo.show'))
        ->assertExactJson(['configured' => false]);

    $this->actingAs($user)
        ->post(route('identify.photo.store'), ['photo' => UploadedFile::fake()->image('card.jpg')], ['Accept' => 'application/json'])
        ->assertStatus(503)
        ->assertJsonPath('message', fn (string $message): bool => str_contains($message, 'ANTHROPIC_API_KEY'));
});

test('non-image uploads are rejected before calling the model', function () {
    Http::preventStrayRequests();

    $this->actingAs(User::factory()->create())
        ->post(route('identify.photo.store'), ['photo' => UploadedFile::fake()->create('card.pdf', 10, 'application/pdf')], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('photo');
});

test('identification is rate limited per user', function () {
    Http::preventStrayRequests();
    Http::fake(['api.anthropic.com/v1/messages' => Http::response(claudeReply('{"confidence": "low"}'))]);

    $user = User::factory()->create();

    foreach (range(1, 10) as $attempt) {
        $this->actingAs($user)
            ->post(route('identify.photo.store'), ['photo' => UploadedFile::fake()->image('card.jpg')], ['Accept' => 'application/json'])
            ->assertOk();
    }

    $this->actingAs($user)
        ->post(route('identify.photo.store'), ['photo' => UploadedFile::fake()->image('card.jpg')], ['Accept' => 'application/json'])
        ->assertTooManyRequests();

    $this->actingAs(User::factory()->create())
        ->post(route('identify.photo.store'), ['photo' => UploadedFile::fake()->image('card.jpg')], ['Accept' => 'application/json'])
        ->assertOk();
});
