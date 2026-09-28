<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

/**
 * Sets up Laravel Horizon, the dashboard and supervisor for the Redis queues.
 *
 * Horizon runs the queue workers described in config/horizon.php and serves its
 * dashboard at /horizon. Locally everyone may open the dashboard; in every other
 * environment only the users allowed by the viewHorizon gate may.
 *
 * Extending:
 * - Add an email to the list in gate() to let that user open the dashboard outside local.
 * - Uncomment a route*NotificationsTo call in boot() to be told when a queue waits too long.
 */
class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        // Horizon::routeSmsNotificationsTo('15556667777');
        // Horizon::routeMailNotificationsTo('example@example.com');
        // Horizon::routeSlackNotificationsTo('slack-webhook-url', '#channel');
    }

    /**
     * Register the Horizon gate.
     *
     * This gate determines who can access Horizon in non-local environments.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', function ($user = null) {
            return in_array(optional($user)->email, [
                //
            ]);
        });
    }
}
