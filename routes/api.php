<?php

use App\Http\Controllers\V1\ArticleController;
use App\Http\Controllers\V1\AuthController;
use App\Http\Controllers\V1\BrandController;
use App\Http\Controllers\V1\CategoryController;
use App\Http\Controllers\V1\CommentController;
use App\Http\Controllers\V1\FormController;
use App\Http\Controllers\V1\GalleryController;
use App\Http\Controllers\V1\MediaController;
use App\Http\Controllers\V1\PageController;
use App\Http\Controllers\V1\ProjectController;
use App\Http\Controllers\V1\SchemaController;
use App\Http\Controllers\V1\SocialController;
use App\Http\Controllers\V1\TagController;
use App\Models\Subject;
use Illuminate\Support\Facades\Route;

/**
 * Customer API, with no /api prefix.
 *
 * The version is part of the path: /v1/...
 * Register a new route inside this v1 group so Scramble includes it at /docs/api.
 * The whole group sits behind api.key: every request needs an X-Api-Key header with a key
 * from the API settings page, sent from that key's origin (server calls have no Origin).
 * Protected routes need the auth.customer middleware as well. The content routes (articles,
 * pages, brands, projects, galleries, categories, tags, forms, media, schema, socials, and comments) are public and read-only, with a request limit.
 * Sending a comment and sending a form are the public writes; each has its own tighter limit, and both
 * wait in the panel (comments for approval, form messages in the inbox).
 */
Route::prefix('v1')->name('v1.')->middleware('api.key')->group(function () {
    Route::prefix('auth')->name('auth.')->group(function () {
        Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1')->name('login');
        Route::get('me', [AuthController::class, 'me'])->middleware('auth.customer')->name('me');
    });

    Route::middleware('throttle:120,1')->group(function () {
        Route::get('articles', [ArticleController::class, 'index'])->name('articles.index');
        Route::get('articles/{slug}', [ArticleController::class, 'show'])->name('articles.show');
        Route::get('pages', [PageController::class, 'index'])->name('pages.index');
        Route::get('pages/{slug}', [PageController::class, 'show'])->name('pages.show');
        Route::get('brands', [BrandController::class, 'index'])->name('brands.index');
        Route::get('brands/{slug}', [BrandController::class, 'show'])->name('brands.show');
        Route::get('projects', [ProjectController::class, 'index'])->name('projects.index');
        Route::get('projects/{slug}', [ProjectController::class, 'show'])->name('projects.show');
        Route::get('galleries', [GalleryController::class, 'index'])->name('galleries.index');
        Route::get('galleries/{slug}', [GalleryController::class, 'show'])->name('galleries.show');
        Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
        Route::get('categories/{slug}', [CategoryController::class, 'show'])->name('categories.show');
        Route::get('tags', [TagController::class, 'index'])->name('tags.index');
        Route::get('tags/{slug}', [TagController::class, 'show'])->name('tags.show');
        Route::get('forms', [FormController::class, 'index'])->name('forms.index');
        Route::get('forms/{slug}', [FormController::class, 'show'])->name('forms.show');
        Route::get('media', [MediaController::class, 'index'])->name('media.index');
        Route::get('media/{key}', [MediaController::class, 'show'])->name('media.show');
        Route::get('schema', [SchemaController::class, 'index'])->name('schema.index');
        Route::get('socials', [SocialController::class, 'index'])->name('socials.index');

        Route::get('{type}/{slug}/comments', [CommentController::class, 'index'])
            ->whereIn('type', Subject::values())
            ->name('comments.index');
    });

    Route::post('{type}/{slug}/comments', [CommentController::class, 'store'])
        ->whereIn('type', Subject::values())
        ->middleware('throttle:comments')
        ->name('comments.store');

    Route::post('forms/{slug}', [FormController::class, 'store'])
        ->middleware('throttle:forms')
        ->name('forms.store');
});
