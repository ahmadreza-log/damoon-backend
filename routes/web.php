<?php

use App\Http\Controllers\InstallController;
use App\Http\Middleware\SetPersianLocale;
use Illuminate\Support\Facades\Route;

/**
 * Web routes.
 *
 * /install stays open only until install finishes. Filament registers the panel at /admin.
 * API routes are not here. They live in routes/api.php under /v1.
 */
Route::middleware(SetPersianLocale::class)->group(function () {
    Route::get('/install', [InstallController::class, 'create'])->name('install');
    Route::post('/install', [InstallController::class, 'store'])->name('install.store');
});

Route::get('/', function () {
    return view('welcome');
});
