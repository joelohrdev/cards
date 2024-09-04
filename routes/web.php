<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::view('/', 'welcome');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Volt::route('cards/create', 'cardform')
    ->middleware(['auth'])
    ->name('cards.create');

Route::get('cards/{card:uuid}', \App\Livewire\CardShow::class)
    ->middleware('auth')
    ->name('cards.show');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

require __DIR__ . '/auth.php';
