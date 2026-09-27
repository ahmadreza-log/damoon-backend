<?php

namespace App\Providers;

use App\Auth\AccessTokens;
use App\Filament\Auth\LogoutResponse;
use App\Models\Customer;
use Filament\Auth\Http\Responses\Contracts\LogoutResponse as LogoutResponseContract;
use Illuminate\Auth\RequestGuard;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(LogoutResponseContract::class, LogoutResponse::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
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
