<?php

namespace App\Models;

use App\Models\Concerns\Taxonomy;
use Database\Factories\TagFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A label that can be attached to many articles, optionally inside a parent tag.
 *
 * Slug, parent, children, and sidebar banners come from Taxonomy, shared with Category.
 *
 * Extending:
 * - Add a column in a tags migration, Fillable, and TagResource together.
 * - The article form creates tags with the TagResource form.
 */
#[Fillable(['name', 'slug', 'parent_id', 'description', 'banners', 'questions'])]
class Tag extends Model
{
    /** @use HasFactory<TagFactory> */
    use HasFactory;

    use Taxonomy;

    /** The public folder for sidebar banner images. */
    public const FOLDER = 'tags/banners';

    /**
     * Articles that carry this tag.
     *
     * @return BelongsToMany<Article, $this>
     */
    public function articles(): BelongsToMany
    {
        return $this->belongsToMany(Article::class);
    }

    /**
     * Column casts.
     *
     * Eloquent owns this method name.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'banners' => 'array',
            'questions' => 'array',
        ];
    }
}
