<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A product an article can point at.
 *
 * Extending:
 * - The article form creates a product inline. A product page would read this model.
 */
#[Fillable(['title'])]
class Product extends Model
{
    /**
     * Articles that list this product as related.
     *
     * @return BelongsToMany<Article, $this>
     */
    public function articles(): BelongsToMany
    {
        return $this->belongsToMany(Article::class);
    }
}
