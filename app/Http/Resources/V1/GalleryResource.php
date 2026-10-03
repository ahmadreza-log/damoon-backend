<?php

namespace App\Http\Resources\V1;

use App\Models\Gallery;
use App\Models\Kind;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A gallery in a list: title, slug, address on the site, kind, description, how many files it has,
 * and a cover.
 *
 * The cover is the first picture of a picture gallery; video and audio galleries have none.
 *
 * Extending:
 * - A field every list needs goes here; one only the detail needs goes in GalleryDetailResource.
 *
 * @mixin Gallery
 */
class GalleryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $paths = $this->paths();

        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'url' => $this->link(),
            'kind' => $this->kind,
            'description' => $this->description,
            'count' => count($paths),
            'cover' => $this->kind === Kind::Image ? Picture::make($paths[0] ?? null) : null,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
