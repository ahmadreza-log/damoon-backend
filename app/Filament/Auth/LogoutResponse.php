<?php

namespace App\Filament\Auth;

use App\Auth\AccessTokens;
use Filament\Auth\Http\Responses\Contracts\LogoutResponse as Responsable;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Livewire\Features\SupportRedirects\Redirector;

/**
 * Panel logout.
 *
 * Besides closing the Filament session, this deletes the Sanctum token stored in the cookie
 * and expires the cookie itself. Without that, the next request would sign the user in again.
 */
class LogoutResponse implements Responsable
{
    /**
     * Revokes the panel token and redirects to the login page.
     *
     * Filament's LogoutResponse contract owns this method name.
     */
    public function toResponse($request): RedirectResponse|Redirector
    {
        $cookie = (string) config('sanctum.panel_cookie');
        $plain = $request->cookie($cookie);

        if (is_string($plain) && $plain !== '') {
            app(AccessTokens::class)->revoke($plain);
        }

        return redirect()
            ->to(Filament::hasLogin() ? Filament::getLoginUrl() : Filament::getUrl())
            ->withCookie(cookie()->forget($cookie));
    }
}
