<?php

namespace App\Http\Resources\V1;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One category or tag with everything its archive page shows.
 *
 * Besides the list fields: the description, the trail of parent names, the parent and
 * children, sidebar banners with their pictures, and questions. Its articles are listed
 * by /v1/articles with the category or tag filter.
 *
 * Extending:
 * - A new Taxonomy column is one more key here.
 *
 * @mixin Category
 */
class TermDetailResource extends JsonResource
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
            'description' => $this->description,
            'trail' => $this->trail(),
            'parent' => new TermResource($this->whenLoaded('parent')),
            'children' => TermResource::collection($this->whenLoaded('children')),
            'banners' => self::banners($this->banners),
            'questions' => Questions::make($this->questions),
        ];
    }

    /**
     * Sidebar banners with their pictures, skipping broken rows.
     *
     * @return array<int, array{image: array{path: string, url: string, sizes: array{thumb: string, small: string, medium: string, large: string}}|null, title: string|null, link: string|null}>
     */
    private static function banners(mixed $rows): array
    {
        $list = [];

        foreach ((array) $rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $list[] = [
                'image' => Picture::make($row['image'] ?? null),
                'title' => is_string($row['title'] ?? null) ? $row['title'] : null,
                'link' => is_string($row['link'] ?? null) ? $row['link'] : null,
            ];
        }

        return $list;
    }
}
