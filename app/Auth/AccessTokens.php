<?php

namespace App\Auth;

use App\Models\Customer;
use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Cookie;

class AccessTokens
{
    public const ABILITY_PANEL = 'panel';

    public const ABILITY_API = 'api';

    public function issue(User|Customer $subject, string $name, string $ability, int $minutes): string
    {
        return $subject->createToken($name, [$ability], now()->addMinutes($minutes))->plainTextToken;
    }

    public function panelCookie(User $user, int $minutes): Cookie
    {
        return $this->makeCookie(
            $this->issue($user, self::ABILITY_PANEL, self::ABILITY_PANEL, $minutes),
            $minutes,
        );
    }

    public function resolve(string $plainText, string $ability, string $modelClass): User|Customer|null
    {
        $accessToken = PersonalAccessToken::findToken($plainText);

        if (! $accessToken) {
            return null;
        }

        if ($accessToken->expires_at?->isPast()) {
            $accessToken->delete();

            return null;
        }

        $tokenable = $accessToken->tokenable;

        if (! $accessToken->can($ability) || ! $tokenable instanceof $modelClass) {
            return null;
        }

        $accessToken->forceFill(['last_used_at' => now()])->save();

        return $tokenable->withAccessToken($accessToken);
    }

    public function refreshPanelCookie(string $plainText): void
    {
        $accessToken = PersonalAccessToken::findToken($plainText);

        if (! $accessToken?->expires_at || ! $accessToken->created_at) {
            return;
        }

        $lifetimeMinutes = max(1, (int) round(abs($accessToken->created_at->diffInMinutes($accessToken->expires_at))));
        $remainingSeconds = $accessToken->expires_at->getTimestamp() - time();

        if ($remainingSeconds >= ($lifetimeMinutes * 60) / 2) {
            return;
        }

        $accessToken->forceFill([
            'expires_at' => now()->addMinutes($lifetimeMinutes),
        ])->save();

        cookie()->queue($this->makeCookie($plainText, $lifetimeMinutes));
    }

    public function revoke(string $plainText): void
    {
        PersonalAccessToken::findToken($plainText)?->delete();
    }

    private function makeCookie(string $plainText, int $minutes): Cookie
    {
        return cookie()->make(
            name: (string) config('sanctum.panel_cookie'),
            value: $plainText,
            minutes: $minutes,
            path: '/',
            secure: (bool) config('session.secure'),
            httpOnly: true,
            sameSite: config('session.same_site') ?: 'lax',
        );
    }
}
