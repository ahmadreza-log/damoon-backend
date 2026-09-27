<?php

namespace App\Http\Middleware;

use App\Auth\AccessTokens;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticatePanelToken
{
    public function __construct(private readonly AccessTokens $tokens) {}

    public function handle(Request $request, Closure $next): Response
    {
        $guard = Auth::guard('web');
        $cookie = (string) config('sanctum.panel_cookie');
        $plainText = $request->cookie($cookie);

        if (! is_string($plainText) || $plainText === '') {
            if ($guard->check()) {
                $guard->logout();
            }

            return $next($request);
        }

        $user = $this->tokens->resolve($plainText, AccessTokens::ABILITY_PANEL, User::class);

        if (! $user instanceof User) {
            if ($guard->check()) {
                $guard->logout();
            }

            cookie()->queue(cookie()->forget($cookie));

            return $next($request);
        }

        if ($guard->id() !== $user->getAuthIdentifier()) {
            $guard->login($user);
        }

        $this->tokens->refreshPanelCookie($plainText);

        return $next($request);
    }
}
