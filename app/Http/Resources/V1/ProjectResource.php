<?php

namespace App\Http\Resources\V1;

use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A project in a list: title, slug, a short excerpt, the client's brand logo, year, industry, and location.
 *
 * The excerpt is the start of the description as plain text.
 *
 * Extending:
 * - A field every list needs goes here; one only the detail needs goes in ProjectDetailResource.
 *
 * @mixin Project
 */
class ProjectResource extends JsonResource
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
            'excerpt' => $this->getSEODescription(),
            'logo' => Picture::make($this->logo),
            'year' => $this->year,
            'industry' => $this->industry,
            'location' => $this->location,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
