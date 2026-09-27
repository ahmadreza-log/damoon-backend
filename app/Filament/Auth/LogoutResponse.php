<?php

namespace App\Filament\Auth;

use App\Auth\AccessTokens;
use Filament\Auth\Http\Responses\Contracts\LogoutResponse as Responsable;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Livewire\Features\SupportRedirects\Redirector;

class LogoutResponse implements Responsable
{
    public function toResponse($request): RedirectResponse|Redirector
    {
        $cookie = (string) config('sanctum.panel_cookie');
        $plainText = $request->cookie($cookie);

        if (is_string($plainText) && $plainText !== '') {
            app(AccessTokens::class)->revoke($plainText);
        }

        return redirect()
            ->to(Filament::hasLogin() ? Filament::getLoginUrl() : Filament::getUrl())
            ->withCookie(cookie()->forget($cookie));
    }
}
