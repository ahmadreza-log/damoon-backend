<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * The one-time install settings.
 *
 * While installed_at is empty, the panel redirects to the install page.
 * This record's title is the panel brand name.
 * installed and brand run on every panel request, so both are kept in the cache.
 * Saving or deleting a record clears both keys.
 *
 * Extending:
 * - Add a new setting in fillable, casts when needed, the install form, and current.
 * - Do not reopen install by clearing installed_at. The owner account already exists.
 * - A new cached value needs its own key constant and a forget in booted.
 */
class Setting extends Model
{
    /** Cache key that marks the install as finished. */
    public const INSTALLED = 'settings.installed';

    /** Cache key for the panel brand name. */
    public const BRAND = 'settings.brand';

    /**
     * Columns the install page may write in one create() call.
     *
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'description',
        'installed_at',
    ];

    /**
     * installed_at is when install finished, not when the row was inserted.
     *
     * Eloquent owns this method name.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'installed_at' => 'datetime',
        ];
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
