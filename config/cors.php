<?php

/**
 * Cross-origin rules for the customer API.
 *
 * The public website is not served by this Laravel app, so browsers call /v1 from another
 * origin. Any origin may pass the CORS check here because App\Http\Middleware\RequireApiKey
 * decides who gets in: each key opens only its own origin. Preflight requests carry no key and
 * are answered by Laravel's HandleCors before routing.
 *
 * Extending:
 * - Keep X-Api-Key allowed (allowed_headers is * here); browsers send it on every call.
 * - A new public path outside /v1 needs its own entry in paths.
 */
return [

    'paths' => ['v1', 'v1/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 600,

    'supports_credentials' => false,

];
