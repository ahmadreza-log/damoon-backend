<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class Setting extends Model
{
    protected $fillable = [
        'title',
        'description',
        'installed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'installed_at' => 'datetime',
        ];
    }

    public static function isInstalled(): bool
    {
        if (! Schema::hasTable('settings')) {
            return false;
        }

        return static::query()->whereNotNull('installed_at')->exists();
    }

    public static function current(): ?self
    {
        if (! Schema::hasTable('settings')) {
            return null;
        }

        return static::query()->latest('id')->first();
    }
}
