<?php

namespace App\Providers;

use App\Auth\AccessTokens;
use App\Filament\Auth\LogoutResponse;
use App\Models\Customer;
use App\Models\FormSetting;
use App\Models\Role;
use App\Models\User;
use App\Support\Seo;
use App\Support\Shamsi;
use Filament\Auth\Http\Responses\Contracts\LogoutResponse as LogoutResponseContract;
use Filament\Support\Assets\AlpineComponent;
use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Auth\RequestGuard;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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
 * - Named request limits, such as comments for sending comments and forms for sending forms (its rate
 *   comes from the forms settings), are defined in boot with RateLimiter::for.
 * - permission.models.role points at App\Models\Role so a role can keep a Persian name.
 * - Shamsi dates are applied in Shamsi::boot after the other providers boot.
 * - The SEO box fields are adjusted in Seo::boot.
 * - The panel stylesheet and the page builder and form builder scripts and styles are built into
 *   resources/dist with npm run panel, npm run designer, and npm run formbuilder and registered here;
 *   php artisan filament:assets copies them into public. Only the panel stylesheet loads on every page.
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

        // The SEO box writes a seo_meta row only when something is filled, in the panel's locale.
        config(['seo.features.auto_create_meta' => false]);

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

        Seo::boot();

        FilamentAsset::register([
            Css::make('panel', resource_path('dist/panel.css')),
            AlpineComponent::make('designer', resource_path('dist/designer.js')),
            Css::make('designer', resource_path('dist/designer.css'))->loadedOnRequest(),
            AlpineComponent::make('formbuilder', resource_path('dist/formbuilder.js')),
            Css::make('formbuilder', resource_path('dist/formbuilder.css'))->loadedOnRequest(),
        ]);

        // Without an app version the builder files carry Filament's version, and browsers keep an old build after a rebuild.
        FilamentAsset::appVersion((string) max(array_map(
            fn (string $file): int => (int) @filemtime(resource_path('dist/'.$file)),
            ['panel.css', 'designer.js', 'designer.css', 'formbuilder.js', 'formbuilder.css'],
        )));

        $this->app->booted(function (): void {
            Shamsi::boot();
        });

        // Counted apart from the reading limit, so browsing a site does not use up a visitor's comments.
        RateLimiter::for('comments', fn (Request $request): Limit => Limit::perMinute(5)->by('comments|'.$request->ip()));
        RateLimiter::for('forms', fn (Request $request): Limit => Limit::perMinute(max(1, FormSetting::current()->rate))->by('forms|'.$request->ip()));

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
