<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A group an article can belong to.
 *
 * Extending:
 * - The article form creates a category inline. A category page would read this model.
 */
#[Fillable(['name'])]
class Category extends Model
{
    /**
     * Articles in this category.
     *
     * @return HasMany<Article, $this>
     */
    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }
}
