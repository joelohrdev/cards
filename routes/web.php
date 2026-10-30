<?php

use App\Http\Controllers\Catalog\CardController;
use App\Http\Controllers\Catalog\CardSetController;
use App\Http\Controllers\Catalog\CatalogLookupController;
use App\Http\Controllers\Catalog\ChecklistController;
use App\Http\Controllers\Catalog\ParallelController;
use App\Http\Controllers\Catalog\SetOwnershipController;
use App\Http\Controllers\Collection\BulkCollectionItemController;
use App\Http\Controllers\Collection\CollectionItemController;
use App\Http\Controllers\Collection\ItemImageController;
use App\Http\Controllers\Collection\PriceCompController;
use App\Http\Controllers\Collection\QuickAddController;
use App\Http\Controllers\Collection\QuickSearchController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Organize\StorageLocationController;
use App\Http\Controllers\Organize\TagController;
use App\Http\Controllers\Organize\WantListController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => auth()->check() ? to_route('dashboard') : to_route('login'))->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::get('search', QuickSearchController::class)->name('search');

    Route::inertia('help', 'help')->name('help');

    Route::get('collection/quick-add', [QuickAddController::class, 'create'])->name('collection.quick-add');
    Route::post('collection/quick-add', [QuickAddController::class, 'store'])->name('collection.quick-add.store');
    Route::post('collection/bulk', BulkCollectionItemController::class)->name('collection.bulk');
    Route::resource('collection', CollectionItemController::class)->parameters(['collection' => 'item']);
    Route::post('collection/{item}/images', [ItemImageController::class, 'store'])->name('collection.images.store');
    Route::patch('collection/{item}/images/{image}', [ItemImageController::class, 'update'])->name('collection.images.update');
    Route::delete('collection/{item}/images/{image}', [ItemImageController::class, 'destroy'])->name('collection.images.destroy');
    Route::post('collection/{item}/comps', [PriceCompController::class, 'store'])->name('collection.comps.store');
    Route::post('collection/{item}/comps/apply', [PriceCompController::class, 'apply'])->name('collection.comps.apply');
    Route::delete('comps/{comp}', [PriceCompController::class, 'destroy'])->name('comps.destroy');

    Route::get('catalog/lookup/cards', [CatalogLookupController::class, 'cards'])->name('catalog.lookup.cards');
    Route::get('catalog/lookup/sets', [CatalogLookupController::class, 'sets'])->name('catalog.lookup.sets');
    Route::resource('sets', CardSetController::class)->except(['edit']);
    Route::post('sets/{set}/checklist', [ChecklistController::class, 'store'])->name('sets.checklist.store');
    Route::post('sets/{set}/owned', [SetOwnershipController::class, 'store'])->name('sets.owned.store');
    Route::post('sets/{set}/parallels', [ParallelController::class, 'store'])->name('sets.parallels.store');
    Route::delete('sets/{set}/parallels/{parallel}', [ParallelController::class, 'destroy'])->name('sets.parallels.destroy');
    Route::patch('cards/{card}', [CardController::class, 'update'])->name('cards.update');
    Route::delete('cards/{card}', [CardController::class, 'destroy'])->name('cards.destroy');

    Route::get('tags', [TagController::class, 'index'])->name('tags.index');
    Route::post('tags', [TagController::class, 'store'])->name('tags.store');
    Route::patch('tags/{tag}', [TagController::class, 'update'])->name('tags.update');
    Route::delete('tags/{tag}', [TagController::class, 'destroy'])->name('tags.destroy');

    Route::resource('locations', StorageLocationController::class)->except(['create', 'edit']);

    Route::get('want-list', [WantListController::class, 'index'])->name('want-list.index');
    Route::post('want-list', [WantListController::class, 'store'])->name('want-list.store');
    Route::patch('want-list/{wantListEntry}', [WantListController::class, 'update'])->name('want-list.update');
    Route::delete('want-list/{wantListEntry}', [WantListController::class, 'destroy'])->name('want-list.destroy');
});

require __DIR__.'/settings.php';
require __DIR__.'/selling.php';
require __DIR__.'/ebay.php';
require __DIR__.'/data.php';
