<?php

use App\Http\Controllers\Selling\CertLookupController;
use App\Http\Controllers\Selling\GradingItemController;
use App\Http\Controllers\Selling\GradingLookupController;
use App\Http\Controllers\Selling\GradingResultController;
use App\Http\Controllers\Selling\GradingSubmissionController;
use App\Http\Controllers\Selling\PhotoIdentificationController;
use App\Http\Controllers\Selling\SaleController;
use App\Http\Controllers\Selling\TradeController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('grading/candidates', [GradingLookupController::class, 'candidates'])->name('grading.candidates');
    Route::get('grading/preparing', [GradingLookupController::class, 'preparing'])->name('grading.preparing');
    Route::resource('grading', GradingSubmissionController::class)
        ->parameters(['grading' => 'submission'])
        ->except(['edit']);
    Route::post('grading/{submission}/items', [GradingItemController::class, 'store'])->name('grading.items.store');
    Route::patch('grading/{submission}/items/{item}', [GradingItemController::class, 'update'])->name('grading.items.update');
    Route::delete('grading/{submission}/items/{item}', [GradingItemController::class, 'destroy'])->name('grading.items.destroy');
    Route::post('grading/{submission}/results', [GradingResultController::class, 'store'])->name('grading.results.store');

    Route::get('sales', [SaleController::class, 'index'])->name('sales.index');
    Route::get('sales/export', [SaleController::class, 'export'])->name('sales.export');
    Route::post('sales', [SaleController::class, 'store'])->name('sales.store');
    Route::delete('sales/{sale}', [SaleController::class, 'destroy'])->name('sales.destroy');
    Route::post('trades', [TradeController::class, 'store'])->name('trades.store');

    Route::get('certs/lookup', CertLookupController::class)->name('certs.lookup');

    Route::get('identify/photo', [PhotoIdentificationController::class, 'show'])->name('identify.photo.show');
    Route::post('identify/photo', [PhotoIdentificationController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('identify.photo.store');
});
