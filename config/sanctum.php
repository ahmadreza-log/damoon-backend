<?php

use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Laravel\Sanctum\Http\Middleware\AuthenticateSession;
use Laravel\Sanctum\Sanctum;

/**
 * Sanctum settings for the staff panel cookie and the customer API tokens.
 *
 * Both sides use personal access tokens issued through App\Auth\AccessTokens.
 * Staff carry theirs in the panel cookie; customers send theirs as a Bearer header.
 * All lifetimes below are in minutes.
 *
 * Extending:
 * - Change a lifetime through its .env key instead of editing the default here.
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Stateful Domains
    |--------------------------------------------------------------------------
    |
    | Requests from these hosts may authenticate with the session cookie, as a
    | single-page front end on the same site would. Set SANCTUM_STATEFUL_DOMAINS
    | to a comma-separated list in production.
    |
    */

    'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', sprintf(
        '%s%s',
        'localhost,localhost:3000,127.0.0.1,127.0.0.1:8000,::1',
        Sanctum::currentApplicationUrlWithPort(),
    ))),

    /*
    |--------------------------------------------------------------------------
    | Session Guards
    |--------------------------------------------------------------------------
    |
    | The guards Sanctum checks before it looks for a token. web is the staff
    | session that AuthenticatePanelToken fills from the panel cookie.
    |
    */

    'guard' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Default Expiration
    |--------------------------------------------------------------------------
    |
    | null means Sanctum itself never expires tokens. Every token is issued with
    | its own expires_at instead, from the lifetimes below.
    |
    */

    'expiration' => null,

    /*
    |--------------------------------------------------------------------------
    | Panel Cookie
    |--------------------------------------------------------------------------
    |
    | The name of the HttpOnly cookie that carries the staff panel token. Login
    | sets it, AuthenticatePanelToken reads it, and logout clears it.
    |
    */

    'panel_cookie' => 'panel_token',

    /*
    |--------------------------------------------------------------------------
    | Token Lifetimes
    |--------------------------------------------------------------------------
    |
    | panel_expiration: a normal panel login (2 hours). The token is renewed for
    | the same length once half of it has passed, so active staff stay signed in.
    | api_expiration: a customer API token (1 hour).
    | remember_expiration: a panel login with "remember me" ticked (30 days).
    |
    */

    'panel_expiration' => (int) env('SANCTUM_PANEL_EXPIRATION', 120),

    'api_expiration' => (int) env('SANCTUM_API_EXPIRATION', 60),

    'remember_expiration' => (int) env('SANCTUM_REMEMBER_EXPIRATION', 43200),

    /*
    |--------------------------------------------------------------------------
    | Token Prefix
    |--------------------------------------------------------------------------
    |
    | An optional prefix added to every new token, which helps secret scanners
    | recognise leaked tokens. Empty by default.
    |
    */

    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),

    /*
    |--------------------------------------------------------------------------
    | Sanctum Middleware
    |--------------------------------------------------------------------------
    |
    | The middleware Sanctum uses for stateful requests: session checks,
    | cookie encryption, and CSRF protection.
    |
    */

    'middleware' => [
        'authenticate_session' => AuthenticateSession::class,
        'encrypt_cookies' => EncryptCookies::class,
        'validate_csrf_token' => ValidateCsrfToken::class,
    ],

];
