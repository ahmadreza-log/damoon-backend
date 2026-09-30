<?php

namespace App\Models;

use App\Models\Concerns\Taxonomy;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A group articles can belong to, optionally inside a parent category.
 * An article can sit in many categories through the article_category table.
 *
 * Slug, parent, children, and sidebar banners come from Taxonomy, shared with Tag.
 *
 * Extending:
 * - Add a column in a categories migration, Fillable, and CategoryResource together.
 * - The article form creates categories with the CategoryResource form.
 */
#[Fillable(['name', 'slug', 'parent_id', 'description', 'banners', 'questions'])]
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    use Taxonomy;

    /** The public folder for sidebar banner images. */
    public const FOLDER = 'categories/banners';

    /**
     * Articles in this category. An article can sit in more than one category.
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
