<?php

namespace App\Http\Resources\V1;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A category or tag in a list: name, slug, parent, and how many published articles it has.
 *
 * Categories and tags share their columns through Taxonomy, so one resource serves both.
 * articles_count is sent only when the query counted it.
 *
 * Extending:
 * - A field every list needs goes here; one only the detail needs goes in TermDetailResource.
 *
 * @mixin Category
 */
class TermResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'parent_id' => $this->parent_id,
            'articles_count' => $this->whenCounted('articles'),
        ];
    }
}
