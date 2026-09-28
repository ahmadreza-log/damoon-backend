<?php

namespace App\Http\Resources\V1;

use App\Models\Page;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A published page in a list: title, slug, where it sits in the page tree, and its cover.
 *
 * parent_id and position are enough for a site to build its menu tree from the list.
 *
 * Extending:
 * - A field every list needs goes here; one only the detail needs goes in PageDetailResource.
 *
 * @mixin Page
 */
class PageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'url' => $this->getUrlForSEO(),
            'parent_id' => $this->parent_id,
            'position' => $this->position,
            'cover' => Picture::make($this->cover),
            'published_at' => $this->published_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
