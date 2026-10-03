<?php

namespace App\Models;

use App\Support\Frontend;
use App\Support\Icons;
use Damoon\Schema\Concerns\HasSchemas;
use Damoon\Schema\Contracts\Schemable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * The one-time install settings.
 *
 * While installed_at is empty, the panel redirects to the install page.
 * This record's title is the panel brand name. url is the public website's address and routes
 * holds each content type's address pattern; App\Support\Frontend builds every site address from
 * them. schemas holds the site-wide schema.org items (Organization and WebSite to start), edited
 * on the general settings page. socials holds the site's social links, edited on the social
 * networks settings page.
 * installed and brand run on every panel request, so both are kept in the cache.
 * Saving or deleting a record clears both keys and the request's Frontend.
 *
 * Extending:
 * - Add a new setting in fillable, casts when needed, the install form, and current.
 * - Do not reopen install by clearing installed_at. The owner account already exists.
 * - A new cached value needs its own key constant and a forget in booted.
 */
class Setting extends Model implements Schemable
{
    use HasSchemas;

    /** Cache key that marks the install as finished. */
    public const INSTALLED = 'settings.installed';

    /** Cache key for the panel brand name. */
    public const BRAND = 'settings.brand';

    /** The site-wide schema types the site starts with. */
    public const SCHEMAS = ['Organization', 'WebSite'];

    /**
     * Columns the install page may write in one create() call.
     *
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'description',
        'installed_at',
        'socials',
        'url',
        'routes',
        'schemas',
    ];

    /**
     * installed_at is when install finished, not when the row was inserted. socials and schemas
     * are JSON lists; routes is a JSON object of address patterns.
     *
     * Eloquent owns this method name.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'installed_at' => 'datetime',
            'socials' => 'array',
            'routes' => 'array',
            'schemas' => 'array',
        ];
    }

    /**
     * The site-wide schema types a fresh site starts with.
     *
     * @return list<string>
     */
    public static function blueprints(): array
    {
        return self::SCHEMAS;
    }

    /**
     * The site's social links in order, leaving out rows without a name, a known icon, or an address.
     *
     * @return list<array{name: string, icon: string, url: string}>
     */
    public function links(): array
    {
        $links = [];

        foreach ((array) $this->socials as $row) {
            $name = is_array($row) ? trim((string) ($row['name'] ?? '')) : '';
            $icon = is_array($row) ? (string) ($row['icon'] ?? '') : '';
            $url = is_array($row) ? trim((string) ($row['url'] ?? '')) : '';

            if ($name === '' || $url === '' || ! Icons::exists($icon)) {
                continue;
            }

            $links[] = ['name' => $name, 'icon' => $icon, 'url' => $url];
        }

        return $links;
    }

    /**
     * Clears the cached install flag and brand name whenever a record changes.
     *
     * Eloquent owns this method name.
     */
    protected static function booted(): void
    {
        $forget = function (): void {
            Cache::forget(self::INSTALLED);
            Cache::forget(self::BRAND);
            app()->forgetInstance(Frontend::class);
        };

        static::saved($forget);
        static::deleted($forget);
    }

    /**
     * Whether install has finished.
     *
     * Only a finished install is cached, since install never reopens. Until then every
     * call reads the table, so the install page and its transaction always see the database.
     * A missing table also counts as not installed, so migrations and tests do not error.
     */
    public static function installed(): bool
    {
        if (rescue(fn (): mixed => Cache::get(self::INSTALLED), null, false) === true) {
            return true;
        }

        if (! Schema::hasTable('settings')) {
            return false;
        }

        $installed = static::query()->whereNotNull('installed_at')->exists();

        if ($installed) {
            rescue(fn (): bool => Cache::forever(self::INSTALLED, true), null, false);
        }

        return $installed;
    }

    /**
     * The panel brand name: the latest record's title, or the application name.
     *
     * Only the title string is cached, since cache.serializable_classes is off.
     */
    public static function brand(): string
    {
        $title = rescue(
            fn (): string => Cache::rememberForever(self::BRAND, fn (): string => (string) static::current()?->title),
            fn (): string => (string) static::current()?->title,
            false,
        );

        return $title !== '' ? $title : (string) config('app.name');
    }

    /**
     * The latest settings record. The panel title is read from here.
     */
    public static function current(): ?self
    {
        if (! Schema::hasTable('settings')) {
            return null;
        }

        return static::query()->latest('id')->first();
    }
}
