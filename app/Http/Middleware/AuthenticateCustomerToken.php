<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Locks customer routes to the customer guard.
 *
 * The alias is auth.customer, registered in bootstrap/app.php.
 * The sanctum-customer guard reads the token. This middleware only switches the
 * default guard and returns 401 when no customer is present.
 *
 * Extending:
 * - Put this middleware only on routes that require a customer Bearer token.
 * - A panel token is rejected here on purpose, because its ability and model differ.
 */
class AuthenticateCustomerToken
{
    /**
     * Activates the customer guard and answers guests with 401.
     *
     * The Laravel middleware contract owns this method name.
     */
    public function handle(Request $request, Closure $next): Response
    {
        Auth::shouldUse('customer');

        if (! Auth::guard('customer')->check()) {
            return response()->json([
                'message' => 'احراز هویت انجام نشده است.',
            ], 401);
        }

        return $next($request);
    }
}
