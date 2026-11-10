<?php

test('the web app manifest opens the app full-screen under the app’s name', function () {
    config(['app.name' => 'Toploaded']);

    $this->get(route('manifest'))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/manifest+json')
        ->assertJson([
            'name' => 'Toploaded',
            'short_name' => 'Toploaded',
            'start_url' => '/dashboard',
            'display' => 'standalone',
        ]);
});

test('every icon the manifest lists exists, including a maskable one', function () {
    $icons = collect($this->get(route('manifest'))->json('icons'));

    expect($icons->pluck('src')->every(fn (string $src): bool => file_exists(public_path($src))))->toBeTrue()
        ->and($icons->where('purpose', 'maskable'))->not->toBeEmpty()
        ->and($icons->pluck('sizes'))->toContain('192x192', '512x512');
});

test('pages link the manifest and the files the service worker needs are in place', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('<link rel="manifest" href="/manifest.webmanifest">', escape: false);

    expect(public_path('sw.js'))->toBeFile()
        ->and(public_path('offline.html'))->toBeFile();
});
