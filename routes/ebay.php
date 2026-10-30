<?php

use App\Http\Controllers\Ebay\AccountDeletionController;
use App\Http\Controllers\Ebay\EbayConnectionController;
use App\Http\Controllers\Ebay\EbayListingController;
use App\Http\Controllers\Ebay\EbaySettingsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('ebay/settings', [EbaySettingsController::class, 'show'])->name('ebay.settings');
    Route::put('ebay/settings/policies', [EbaySettingsController::class, 'updatePolicies'])->name('ebay.settings.policies');
    Route::put('ebay/settings/location', [EbaySettingsController::class, 'updateLocation'])->name('ebay.settings.location');

    Route::get('ebay/connect', [EbayConnectionController::class, 'redirect'])->name('ebay.connect');
    Route::get('ebay/callback', [EbayConnectionController::class, 'callback'])->name('ebay.callback');
    Route::delete('ebay/connection', [EbayConnectionController::class, 'destroy'])->name('ebay.disconnect');

    Route::get('ebay/listings', [EbayListingController::class, 'index'])->name('ebay.listings.index');
    Route::patch('ebay/listings/{listing}', [EbayListingController::class, 'update'])->name('ebay.listings.update');
    Route::delete('ebay/listings/{listing}', [EbayListingController::class, 'destroy'])->name('ebay.listings.destroy');
    Route::post('ebay/listings/{listing}/publish', [EbayListingController::class, 'publish'])->name('ebay.listings.publish');
    Route::post('ebay/listings/{listing}/end', [EbayListingController::class, 'end'])->name('ebay.listings.end');

    Route::get('ebay/items/{item}/listing', [EbayListingController::class, 'edit'])->name('ebay.items.listing');
    Route::post('ebay/items/{item}/listing', [EbayListingController::class, 'store'])->name('ebay.items.listing.store');
});

Route::get('ebay/notifications/account-deletion', [AccountDeletionController::class, 'challenge'])->name('ebay.notifications.deletion');
Route::post('ebay/notifications/account-deletion', [AccountDeletionController::class, 'store'])
    ->middleware('throttle:60,1')
    ->name('ebay.notifications.deletion.store');
