<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $this->get(route('help'))->assertRedirect(route('login'));
});

test('signed-in users can read the help guide', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('help'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('help'));
});
