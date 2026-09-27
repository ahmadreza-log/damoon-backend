<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

class SetPersianLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        app()->setLocale('fa');
        Carbon::setLocale('fa');

        return $next($request);
    }
}
