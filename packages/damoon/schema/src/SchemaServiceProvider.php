<?php

namespace Damoon\Schema;

use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the package views under the schema:: namespace and the editor stylesheet.
 *
 * php artisan filament:assets copies the stylesheet into public/css/damoon/schema.
 *
 * Extending:
 * - The host application sets the site placeholders with Schemas::site in its own provider.
 */
class SchemaServiceProvider extends ServiceProvider
{
    /**
     * Loads the views and the stylesheet.
     *
     * Laravel owns this method name.
     */
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'schema');

        FilamentAsset::register([
            Css::make('schema', __DIR__.'/../resources/dist/schema.css'),
        ], 'damoon/schema');
    }
}
