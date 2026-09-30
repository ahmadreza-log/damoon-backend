<?php

namespace App\Http\Resources\V1;

use App\Models\Brand;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A brand in a list: title, English title, slug, a short excerpt, logo, and website.
 *
 * The excerpt is the start of the description as plain text.
 *
 * Extending:
 * - A field every list needs goes here; one only the detail needs goes in BrandDetailResource.
 *
 * @mixin Brand
 */
class BrandResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'english' => $this->english,
            'slug' => $this->slug,
            'url' => $this->getUrlForSEO(),
            'excerpt' => $this->getSEODescription(),
            'logo' => Picture::make($this->logo),
            'website' => $this->website,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
