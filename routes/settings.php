<?php

use App\Http\Controllers\Auth\AcceptInvitationController;
use App\Http\Controllers\Settings\InvitationController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use App\Http\Controllers\Settings\UserController;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/security', [SecurityController::class, 'edit'])
        ->middleware(RequirePassword::class)
        ->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::inertia('settings/appearance', 'settings/appearance')->name('appearance.edit');
});

Route::middleware(['auth', 'verified', 'can:manage-team'])->group(function () {
    Route::get('settings/users', [UserController::class, 'index'])->name('users.index');
    Route::patch('settings/users/{member}', [UserController::class, 'update'])->name('users.update');
    Route::delete('settings/users/{member}', [UserController::class, 'destroy'])->name('users.destroy');
    Route::post('settings/users/invitations', [InvitationController::class, 'store'])->name('users.invitations.store');
    Route::delete('settings/users/invitations/{invitation}', [InvitationController::class, 'destroy'])->name('users.invitations.destroy');
});

Route::middleware('guest')->group(function () {
    Route::get('invitations/{token}', [AcceptInvitationController::class, 'show'])->name('invitations.show');
    Route::post('invitations/{token}', [AcceptInvitationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('invitations.accept');
});
