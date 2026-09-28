<?php

use App\Http\Middleware\AuthenticateCustomerToken;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * Builds the Laravel application: routes, middleware, and exception handling.
 *
 * Routes: routes/web.php for the site and install page, routes/console.php for artisan
 * commands, /up as the health check, and routes/api.php for the customer API. The API
 * file is loaded in then() with only the api middleware group, so its paths have no
 * /api prefix and start at /v1. The Filament panel registers its own /admin routes.
 *
 * Extending:
 * - Register new middleware aliases in withMiddleware() and custom exception reporting in withExceptions().
 * - Service providers are listed in bootstrap/providers.php, not here.
 */
return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware('api')->group(__DIR__.'/../routes/api.php');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // auth.customer locks customer Bearer routes. It does not accept a panel token.
        $middleware->alias([
            'auth.customer' => AuthenticateCustomerToken::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // A browser opening an API link has no Accept: application/json header but still expects JSON errors.
        $exceptions->shouldRenderJsonWhen(fn (Request $request): bool => $request->is('v1', 'v1/*') || $request->expectsJson());
    })->create();
