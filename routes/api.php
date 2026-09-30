<?php

use App\Http\Controllers\V1\ArticleController;
use App\Http\Controllers\V1\AuthController;
use App\Http\Controllers\V1\CategoryController;
use App\Http\Controllers\V1\CommentController;
use App\Http\Controllers\V1\MediaController;
use App\Http\Controllers\V1\PageController;
use App\Http\Controllers\V1\TagController;
use App\Models\Subject;
use Illuminate\Support\Facades\Route;

/**
 * Customer API, with no /api prefix.
 *
 * The version is part of the path: /v1/...
 * Register a new route inside this v1 group so Scramble includes it at /docs/api.
 * Protected routes need the auth.customer middleware. The content routes (articles,
 * pages, categories, tags, media, and comments) are public and read-only, with a request limit.
 * Sending a comment is the one public write; it has a tighter limit and waits for approval.
 */
Route::prefix('v1')->name('v1.')->group(function () {
    Route::prefix('auth')->name('auth.')->group(function () {
        Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1')->name('login');
        Route::get('me', [AuthController::class, 'me'])->middleware('auth.customer')->name('me');
    });

    Route::middleware('throttle:120,1')->group(function () {
        Route::get('articles', [ArticleController::class, 'index'])->name('articles.index');
        Route::get('articles/{slug}', [ArticleController::class, 'show'])->name('articles.show');
        Route::get('pages', [PageController::class, 'index'])->name('pages.index');
        Route::get('pages/{slug}', [PageController::class, 'show'])->name('pages.show');
        Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
        Route::get('categories/{slug}', [CategoryController::class, 'show'])->name('categories.show');
        Route::get('tags', [TagController::class, 'index'])->name('tags.index');
        Route::get('tags/{slug}', [TagController::class, 'show'])->name('tags.show');
        Route::get('media', [MediaController::class, 'index'])->name('media.index');
        Route::get('media/{key}', [MediaController::class, 'show'])->name('media.show');

        Route::get('{type}/{slug}/comments', [CommentController::class, 'index'])
            ->whereIn('type', Subject::values())
            ->name('comments.index');
    });

    Route::post('{type}/{slug}/comments', [CommentController::class, 'store'])
        ->whereIn('type', Subject::values())
        ->middleware('throttle:comments')
        ->name('comments.store');
});
