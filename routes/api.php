<?php

use App\Http\Controllers\V1\AuthController;
use Illuminate\Support\Facades\Route;

/**
 * Customer API, with no /api prefix.
 *
 * The version is part of the path: /v1/...
 * Register a new route inside this v1 group so Scramble includes it at /docs/api.
 * Protected routes need the auth.customer middleware.
 */
Route::prefix('v1')->name('v1.')->group(function () {
    Route::prefix('auth')->name('auth.')->group(function () {
        Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1')->name('login');
        Route::post('register', [AuthController::class, 'register'])->name('register');
        Route::post('verify', [AuthController::class, 'verify'])->name('verify');
        Route::post('forgot', [AuthController::class, 'forgot'])->name('forgot');
        Route::get('me', [AuthController::class, 'me'])->middleware('auth.customer')->name('me');
    });
});
