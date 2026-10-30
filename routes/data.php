<?php

use App\Http\Controllers\Data\ExportController;
use App\Http\Controllers\Data\ImportController;
use App\Http\Controllers\Data\ShareLinkController;
use App\Http\Controllers\Data\ShowcaseController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('imports', [ImportController::class, 'index'])->name('imports.index');
    Route::post('imports', [ImportController::class, 'store'])->name('imports.store');
    Route::get('imports/{import}', [ImportController::class, 'show'])->name('imports.show');
    Route::get('imports/{import}/mapping', [ImportController::class, 'mapping'])->name('imports.mapping');
    Route::put('imports/{import}', [ImportController::class, 'update'])->name('imports.update');
    Route::delete('imports/{import}', [ImportController::class, 'destroy'])->name('imports.destroy');
    Route::delete('imports/{import}/cards', [ImportController::class, 'destroyCards'])->name('imports.cards.destroy');

    Route::get('exports', [ExportController::class, 'index'])->name('exports.index');
    Route::get('exports/collection.csv', [ExportController::class, 'collection'])->name('exports.collection');
    Route::get('exports/insurance', [ExportController::class, 'insurance'])->name('exports.insurance');

    Route::get('share-links', [ShareLinkController::class, 'index'])->name('share-links.index');
    Route::post('share-links', [ShareLinkController::class, 'store'])->name('share-links.store');
    Route::patch('share-links/{shareLink}', [ShareLinkController::class, 'update'])->name('share-links.update');
    Route::delete('share-links/{shareLink}', [ShareLinkController::class, 'destroy'])->name('share-links.destroy');
});

Route::get('s/{token}', [ShowcaseController::class, 'show'])
    ->where('token', '[A-Za-z0-9]{20,64}')
    ->middleware('throttle:120,1')
    ->name('showcase');
