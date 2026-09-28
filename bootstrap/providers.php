<?php

/**
 * Service providers Laravel boots for every request and command.
 *
 * AppServiceProvider holds application-wide setup, AdminPanelProvider builds the
 * Filament panel at /admin, and HorizonServiceProvider sets up the queue dashboard.
 *
 * Extending:
 * - Add a new provider class to this list; package providers are discovered automatically.
 */
return [
    App\Providers\AppServiceProvider::class,
    App\Providers\Filament\AdminPanelProvider::class,
    App\Providers\HorizonServiceProvider::class,
];
