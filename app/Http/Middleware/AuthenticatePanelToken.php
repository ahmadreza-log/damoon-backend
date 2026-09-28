<?php

namespace App\Http\Middleware;

use App\Auth\AccessTokens;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Builds the panel session from the Sanctum token cookie.
 *
 * This middleware stands in for an empty session login:
 * a valid cookie with the panel ability logs the user into the web guard.
 * A customer cookie, a broken cookie, an inactive account, or an expired token logs the session out and clears the cookie.
 *
 * Extending:
 * - Keep this class on the panel middleware stack, after StartSession and before AuthenticateSession.
 * - The cookie name is sanctum.panel_cookie.
 */
class AuthenticatePanelToken
{
    /**
     * Receives the token service from the container.
     *
     * AccessTokens resolves, refreshes, and revokes the Sanctum token behind the cookie.
     */
    public function __construct(private readonly AccessTokens $tokens) {}

    /**
     * Reads the panel cookie and logs the user in when it is valid.
     *
     * Once more than half of the token lifetime has passed, refresh renews that same cookie.
     * The Laravel middleware contract owns this method name.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $guard = Auth::guard('web');
        $cookie = (string) config('sanctum.panel_cookie');
        $plain = $request->cookie($cookie);

        if (! is_string($plain) || $plain === '') {
            if ($guard->check()) {
                $guard->logout();
            }

            return $next($request);
        }

        $user = $this->tokens->resolve($plain, AccessTokens::ABILITY_PANEL, User::class);

        if (! $user instanceof User || ! $user->active) {
            $this->tokens->revoke($plain);

            if ($guard->check()) {
                $guard->logout();
            }

            cookie()->queue(cookie()->forget($cookie));

            return $next($request);
        }

        if ($guard->id() !== $user->getAuthIdentifier()) {
            $guard->login($user);
        }

        $token = $user->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $this->tokens->refresh($token, $plain);
        }

        return $next($request);
    }
}
