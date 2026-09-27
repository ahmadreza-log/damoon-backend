<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends the panel to /install until install is finished.
 *
 * It sits on the panel middleware group, not on the install route, so the install form stays open.
 */
class EnsureInstalled
{
    /**
     * Redirects to the install route when installed_at is empty.
     *
     * The Laravel middleware contract owns this method name.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! Setting::installed()) {
            return redirect()->route('install');
        }

        return $next($request);
    }
}
