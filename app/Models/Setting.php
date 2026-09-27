<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * The one-time install settings.
 *
 * While installed_at is empty, the panel redirects to the install page.
 * This record's title is the panel brand name.
 *
 * Extending:
 * - Add a new setting in fillable, casts when needed, the install form, and current.
 * - Do not reopen install by clearing installed_at. The owner account already exists.
 */
class Setting extends Model
{
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
     * Whether install has finished.
     *
     * A missing table also counts as not installed, so migrations and tests do not error.
     */
    public static function installed(): bool
    {
        if (! Schema::hasTable('settings')) {
            return false;
        }

        return static::query()->whereNotNull('installed_at')->exists();
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
