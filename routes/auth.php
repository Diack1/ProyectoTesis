<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('auth/google', [\App\Http\Controllers\Auth\GoogleAuthController::class, 'redirect'])->middleware('throttle:google-access')->name('google.redirect');
    Route::get('auth/google/callback', [\App\Http\Controllers\Auth\GoogleAuthController::class, 'callback'])->middleware('throttle:google-access')->name('google.callback');
    Route::get('auth/google/link', [\App\Http\Controllers\Auth\GoogleAuthController::class, 'linkForm'])->name('google.link');
    Route::post('auth/google/link', [\App\Http\Controllers\Auth\GoogleAuthController::class, 'link'])->middleware('throttle:google-access')->name('google.link.store');
    Route::get('register', [RegisteredUserController::class, 'create'])
        ->name('register');

    Route::post('register', [RegisteredUserController::class, 'store'])->middleware('throttle:account-registration');

    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');

    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->name('password.email');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');

    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->name('password.store');
});

Route::middleware('auth')->group(function () {
    Route::get('verify-email', [\App\Http\Controllers\Auth\CustomerVerificationController::class, 'show'])->name('verification.notice');
    Route::post('verify-email', [\App\Http\Controllers\Auth\CustomerVerificationController::class, 'verify'])->middleware('throttle:customer-verify')->name('verification.verify');
    Route::post('email/verification-notification', [\App\Http\Controllers\Auth\CustomerVerificationController::class, 'send'])->middleware('throttle:customer-code-send')->name('verification.send');

    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])
        ->name('password.confirm');

    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']);

    Route::put('password', [PasswordController::class, 'update'])->name('password.update');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});

Route::middleware(['auth'])->prefix('staff-access')->name('staff-access.')->group(function () {
    Route::get('/', [\App\Http\Controllers\Auth\StaffAccessController::class, 'show'])->name('show');
    Route::get('/request', fn () => redirect()->route('staff-access.show'));
    Route::post('/request', [\App\Http\Controllers\Auth\StaffAccessController::class, 'start'])->name('start');
    Route::post('/verify', [\App\Http\Controllers\Auth\StaffAccessController::class, 'verify'])->middleware('throttle:mfa')->name('verify');
});
