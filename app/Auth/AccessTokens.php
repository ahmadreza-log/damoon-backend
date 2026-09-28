<?php

namespace App\Auth;

use App\Models\Customer;
use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * Issues, reads, and revokes Sanctum tokens.
 *
 * Two abilities stay separate:
 * - panel on the User model, stored in a secure cookie, for the staff panel
 * - api on the Customer model, sent as a Bearer token, for the customer API
 *
 * A token from one side is rejected by the other. resolve enforces that by requiring
 * both the ability and the model class.
 *
 * Extending:
 * - Add a new ability as a constant next to ABILITY_PANEL and ABILITY_API.
 * - A new caller must issue, then resolve, with that same ability and model class.
 * - Lifetimes live in config/sanctum.php: panel_expiration, api_expiration, remember_expiration.
 */
class AccessTokens
{
    /** Ability for the staff panel cookie token. */
    public const ABILITY_PANEL = 'panel';

    /** Ability for the customer Bearer token. */
    public const ABILITY_API = 'api';

    /**
     * Creates a plain-text token with exactly one ability.
     *
     * @param  User|Customer  $subject  Token owner.
     * @param  string  $name  Stored token name. For the panel and the API this is the ability.
     * @param  string  $ability  One of the constants on this class.
     * @param  int  $minutes  Lifetime counted from now.
     */
    public function issue(User|Customer $subject, string $name, string $ability, int $minutes): string
    {
        return $subject->createToken($name, [$ability], now()->addMinutes($minutes))->plainTextToken;
    }

    /**
     * Issues a panel token and places it in a secure cookie.
     */
    public function cookie(User $user, int $minutes): Cookie
    {
        return $this->pack(
            $this->issue($user, self::ABILITY_PANEL, self::ABILITY_PANEL, $minutes),
            $minutes,
        );
    }

    /**
     * Returns the token owner when both the ability and the model class match.
     *
     * An expired token is deleted. A bad token returns null and does not change the session.
     * last_used_at is written at most once a minute, so a busy panel does not update the row on every request.
     * The token is attached to the owner, so currentAccessToken returns it without another lookup.
     *
     * @param  class-string<User|Customer>  $class
     */
    public function resolve(string $plain, string $ability, string $class): User|Customer|null
    {
        $token = PersonalAccessToken::findToken($plain);

        if (! $token) {
            return null;
        }

        if ($token->expires_at?->isPast()) {
            $token->delete();

            return null;
        }

        $owner = $token->tokenable;

        if (! $token->can($ability) || ! $owner instanceof $class) {
            return null;
        }

        if (! $token->last_used_at || $token->last_used_at->lt(now()->subMinute())) {
            $token->forceFill(['last_used_at' => now()])->save();
        }

        return $owner->withAccessToken($token);
    }

    /**
     * Extends the same panel token by its original lifetime once more than half of that lifetime has passed.
     *
     * The token value does not change. Only expires_at and the cookie Max-Age are renewed.
     * If the created or expiry timestamp is missing, nothing happens.
     *
     * @param  PersonalAccessToken  $token  The record resolve already loaded for this cookie.
     * @param  string  $plain  The cookie value, written back with the new Max-Age.
     */
    public function refresh(PersonalAccessToken $token, string $plain): void
    {
        if (! $token->expires_at || ! $token->created_at) {
            return;
        }

        $minutes = max(1, (int) round(abs($token->created_at->diffInMinutes($token->expires_at))));
        $seconds = $token->expires_at->getTimestamp() - time();

        if ($seconds >= ($minutes * 60) / 2) {
            return;
        }

        $token->forceFill([
            'expires_at' => now()->addMinutes($minutes),
        ])->save();

        cookie()->queue($this->pack($plain, $minutes));
    }

    /**
     * Deletes the token. A missing token is not an error.
     */
    public function revoke(string $plain): void
    {
        PersonalAccessToken::findToken($plain)?->delete();
    }

    /**
     * Builds the HttpOnly cookie that carries the panel token.
     *
     * The cookie name comes from sanctum.panel_cookie. Secure and SameSite come from the session config.
     */
    private function pack(string $plain, int $minutes): Cookie
    {
        return cookie()->make(
            name: (string) config('sanctum.panel_cookie'),
            value: $plain,
            minutes: $minutes,
            path: '/',
            secure: (bool) config('session.secure'),
            httpOnly: true,
            sameSite: config('session.same_site') ?: 'lax',
        );
    }
}
