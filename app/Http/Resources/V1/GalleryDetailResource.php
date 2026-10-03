<?php

namespace App\Http\Resources\V1;

use App\Models\Gallery;
use App\Models\Kind;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One gallery with its files in the order staff set.
 *
 * Besides the list fields: items, each file with its address, type, size, the title, alt text,
 * caption, and description written on the media page, and the built sizes of pictures (null
 * for video and audio).
 *
 * Extending:
 * - A new Gallery column is one more key here.
 *
 * @mixin Gallery
 */
class GalleryDetailResource extends JsonResource
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
            'items' => Attachment::list($paths),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
