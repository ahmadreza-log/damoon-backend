<?php

use App\Http\Controllers\InstallController;
use App\Http\Middleware\SetPersianLocale;
use Illuminate\Support\Facades\Route;

Route::middleware(SetPersianLocale::class)->group(function () {
    Route::get('/install', [InstallController::class, 'create'])->name('install');
    Route::post('/install', [InstallController::class, 'store'])->name('install.store');
});

Route::get('/', function () {
    return view('welcome');
});
