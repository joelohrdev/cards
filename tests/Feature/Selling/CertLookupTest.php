<?php

use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['services.psa.token' => 'psa-test-token', 'services.psa.url' => 'https://api.psacard.com/publicapi']);
});

test('a PSA cert is looked up and cached', function () {
    Http::preventStrayRequests();
    Http::fake([
        'api.psacard.com/publicapi/cert/GetByCertNumber/12345678' => Http::response([
            'PSACert' => [
                'CertNumber' => '12345678',
                'Year' => '1986',
                'Brand' => 'FLEER',
                'Category' => 'BASKETBALL CARDS',
                'CardNumber' => '57',
                'Subject' => 'MICHAEL JORDAN',
                'Variety' => '',
                'CardGrade' => 'NM-MT 8',
            ],
        ]),
    ]);

    $user = User::factory()->create();

    foreach (range(1, 2) as $attempt) {
        $this->actingAs($user)
            ->getJson(route('certs.lookup', ['grader' => 'PSA', 'cert_number' => '12345678']))
            ->assertOk()
            ->assertExactJson(['result' => [
                'grader' => 'PSA',
                'cert_number' => '12345678',
                'grade' => '8',
                'year' => 1986,
                'brand' => 'FLEER',
                'set_name' => 'FLEER',
                'card_number' => '57',
                'player' => 'MICHAEL JORDAN',
                'variety' => null,
                'sport' => 'basketball',
            ]]);
    }

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer psa-test-token'));
});

test('a cert PSA doesn’t know returns a helpful not-found message', function () {
    Http::preventStrayRequests();
    Http::fake([
        'api.psacard.com/publicapi/cert/GetByCertNumber/99999999' => Http::response(['IsValidRequest' => true, 'ServerMessage' => 'No data found']),
    ]);

    $this->actingAs(User::factory()->create())
        ->getJson(route('certs.lookup', ['grader' => 'PSA', 'cert_number' => '99999999']))
        ->assertNotFound()
        ->assertJsonPath('message', 'PSA has no cert 99999999. Check the number on the slab label.');
});

test('PSA failing returns a bad gateway message', function () {
    Http::preventStrayRequests();
    Http::fake([
        'api.psacard.com/publicapi/cert/GetByCertNumber/12345678' => Http::response('', 500),
    ]);

    $this->actingAs(User::factory()->create())
        ->getJson(route('certs.lookup', ['grader' => 'PSA', 'cert_number' => '12345678']))
        ->assertStatus(502);
});

test('lookup explains when no PSA token is configured', function () {
    config(['services.psa.token' => null]);
    Http::preventStrayRequests();

    $this->actingAs(User::factory()->create())
        ->getJson(route('certs.lookup', ['grader' => 'PSA', 'cert_number' => '12345678']))
        ->assertStatus(503)
        ->assertJsonPath('message', fn (string $message): bool => str_contains($message, 'PSA_API_TOKEN'));
});

test('only PSA certs can be looked up', function () {
    Http::preventStrayRequests();

    $this->actingAs(User::factory()->create())
        ->getJson(route('certs.lookup', ['grader' => 'BGS', 'cert_number' => '12345678']))
        ->assertUnprocessable();
});

test('guests can’t look up certs', function () {
    $this->getJson(route('certs.lookup', ['grader' => 'PSA', 'cert_number' => '1']))->assertUnauthorized();
});
