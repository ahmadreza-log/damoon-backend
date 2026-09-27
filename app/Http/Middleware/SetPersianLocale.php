<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sets the request language to Persian.
 *
 * The Laravel locale and Carbon change, so panel text is Persian.
 * The calendar itself is Shamsi. That conversion lives in App\Support\Shamsi.
 * This middleware runs on the install form and on the panel.
 */
class SetPersianLocale
{
    /**
     * Sets the application locale and Carbon to fa.
     *
     * The Laravel middleware contract owns this method name.
     */
    public function handle(Request $request, Closure $next): Response
    {
        app()->setLocale('fa');
        Carbon::setLocale('fa');

        return $next($request);
    }
}
