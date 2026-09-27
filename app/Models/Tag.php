<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A label that can be attached to many articles.
 *
 * Extending:
 * - The article form creates a tag inline. A tag page would read this model.
 */
#[Fillable(['name'])]
class Tag extends Model
{
    /**
     * Articles that carry this tag.
     *
     * @return BelongsToMany<Article, $this>
     */
    public function articles(): BelongsToMany
    {
        return $this->belongsToMany(Article::class);
    }
}
