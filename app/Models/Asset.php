<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * The words kept for one public file.
 *
 * The file path is the key. Title, alt text, image title, and description
 * are edited on the media detail page.
 *
 * Extending:
 * - A new text field belongs in the assets migration and Fillable together.
 */
#[Fillable(['path', 'title', 'alt', 'caption', 'description'])]
class Asset extends Model
{
    /**
     * The stored words for a path, or a new unsaved row when none exist yet.
     */
    public static function hold(string $path): self
    {
        $asset = static::query()->where('path', $path)->first();

        if ($asset instanceof self) {
            return $asset;
        }

        return new self(['path' => $path]);
    }
}
