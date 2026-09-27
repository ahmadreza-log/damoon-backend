<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateCustomerToken
{
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
