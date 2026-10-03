<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * One private key for the /v1 API, bound to one site origin.
 *
 * The plain key is shown once, when it is made or regenerated; only its sha256 hash is
 * stored in token, so a lost key cannot be read back and has to be regenerated. hint keeps the
 * key's last characters for the panel list. A browser request must come from origin; a request
 * without an Origin header (a server, or a site's server-side rendering) is let in on the key alone.
 * App\Http\Middleware\RequireApiKey runs these checks on every /v1 route.
 *
 * Extending:
 * - A new rule on a key (an expiry date, allowed routes) is a column and a check in allows.
 * - Keep issue and regenerate the only places that build a key, so the hash and hint stay in step.
 */
class ApiKey extends Model
{
    /** The start of every key, so a leaked key is easy to spot in code and logs. */
    public const PREFIX = 'dmk_';

    /** Random characters after the prefix. */
    public const LENGTH = 40;

    /** Seconds between two used_at writes for the same key. */
    public const STAMP = 300;

    /**
     * Columns the panel may write. token and hint are set only by issue and regenerate.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'origin',
        'active',
    ];

    /**
     * The hash never leaves the server.
     *
     * @var list<string>
     */
    protected $hidden = [
        'token',
    ];

    /**
     * Eloquent owns this method name.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'used_at' => 'datetime',
        ];
    }

    /**
     * Stores origin in its short form, so comparisons with the Origin header are exact.
     *
     * Eloquent owns this method name.
     */
    protected static function booted(): void
    {
        static::saving(function (self $key): void {
            $key->origin = self::normalize((string) $key->origin) ?? (string) $key->origin;
        });
    }

    /**
     * Makes and saves a key and returns it with the plain key, which is never stored.
     *
     * @param  array{name: string, origin: string, active?: bool}  $data
     * @return array{0: self, 1: string}
     */
    public static function issue(array $data): array
    {
        $key = new self($data);
        $plain = $key->fill(['active' => $data['active'] ?? true])->renew();
        $key->save();

        return [$key, $plain];
    }

    /**
     * Gives the key a new secret, saves it, and returns the plain key. The old one stops working.
     */
    public function regenerate(): string
    {
        $plain = $this->renew();
        $this->save();

        return $plain;
    }

    /**
     * The active key whose hash matches the plain key, if any.
     */
    public static function match(?string $plain): ?self
    {
        $plain = trim((string) $plain);

        if ($plain === '' || strlen($plain) > 200) {
            return null;
        }

        return self::query()
            ->where('token', hash('sha256', $plain))
            ->where('active', true)
            ->first();
    }

    /**
     * Whether a request with this Origin header may use the key.
     *
     * No header means a server is calling, so the key alone is enough. A header that is not a
     * plain web origin (such as "null" from a sandboxed page) never matches.
     */
    public function allows(?string $header): bool
    {
        if ($header === null || trim($header) === '') {
            return true;
        }

        return self::normalize($header) === $this->origin;
    }

    /**
     * Writes used_at at most once every few minutes, without touching updated_at.
     */
    public function stamp(): void
    {
        if ($this->used_at !== null && $this->used_at->diffInSeconds(now(), true) < self::STAMP) {
            return;
        }

        $this->used_at = now();
        $this->timestamps = false;
        $this->saveQuietly();
        $this->timestamps = true;
    }

    /**
     * The scheme://host[:port] form of an address, lowercased and without a default port.
     *
     * Returns null when the address is not an http or https origin.
     */
    public static function normalize(string $value): ?string
    {
        $value = trim($value);
        $parts = parse_url($value);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        $scheme = strtolower($parts['scheme']);
        $host = strtolower($parts['host']);

        if (! in_array($scheme, ['http', 'https'], true) || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }

        $port = $parts['port'] ?? null;
        $port = ($scheme === 'http' && $port === 80) || ($scheme === 'https' && $port === 443) ? null : $port;

        return $scheme.'://'.$host.($port !== null ? ':'.$port : '');
    }

    /**
     * Sets a fresh token and hint on the model and returns the plain key.
     */
    private function renew(): string
    {
        $plain = self::PREFIX.Str::random(self::LENGTH);

        $this->forceFill([
            'token' => hash('sha256', $plain),
            'hint' => substr($plain, -4),
        ]);

        return $plain;
    }
}
