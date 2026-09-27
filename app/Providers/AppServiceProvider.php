<?php

namespace App\Providers;

use App\Auth\AccessTokens;
use App\Filament\Auth\LogoutResponse;
use App\Models\Customer;
use App\Models\Role;
use App\Models\User;
use App\Support\Shamsi;
use Filament\Auth\Http\Responses\Contracts\LogoutResponse as LogoutResponseContract;
use Illuminate\Auth\RequestGuard;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Application service bindings.
 *
 * Panel logout also revokes the Sanctum token.
 * The sanctum-customer guard accepts only a Bearer token with the api ability on a Customer.
 *
 * Extending:
 * - Add a new guard here with Auth::extend and register its name in config/auth.php.
 * - OpenAPI docs are configured for the v1 prefix in config/scramble.php.
 * - The owner bypass belongs in boot. Section checks stay in the policies.
 * - permission.models.role points at App\Models\Role so a role can keep a Persian name.
 * - Shamsi dates are applied in Shamsi::boot after the other providers boot.
 */
class AppServiceProvider extends ServiceProvider
{
    /**
     * Replaces Filament's logout response with one that revokes the panel token.
     *
     * Laravel owns this method name.
     */
    public function register(): void
    {
        config(['permission.models.role' => Role::class]);

        $this->app->bind(LogoutResponseContract::class, LogoutResponse::class);
    }

    /**
     * Connects the customer guard to the customer Bearer token.
     *
     * Laravel owns this method name.
     */
    public function boot(): void
    {
        // Spatie ships with role events off. The owner lock listens for them.
        config(['permission.events_enabled' => true]);

        $this->app->booted(function (): void {
            Shamsi::boot();
        });

        Gate::before(function (mixed $user, string $ability, array $arguments): ?bool {
            if ($user instanceof User && $user->owner()) {
                return true;
            }

            return null;
        });

        Auth::extend('sanctum-customer', function ($app, string $name, array $config): RequestGuard {
            return new RequestGuard(function ($request) use ($app) {
                $token = $request->bearerToken();

                if (! is_string($token) || $token === '') {
                    return null;
                }

                return $app->make(AccessTokens::class)->resolve($token, AccessTokens::ABILITY_API, Customer::class);
            }, $app['request'], $app['auth']->createUserProvider($config['provider'] ?? null));
        });
    }
}
