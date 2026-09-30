<?php

namespace App\Http\Resources\V1;

use App\Models\Brand;
use App\Support\Library;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One brand with everything its site page shows.
 *
 * Besides the list fields: the description as a Tiptap JSON document through Document, the
 * features, the LinkedIn and software download links, the catalog file with its full address,
 * whether comments are open with the approved comment count, and the resolved SEO for the page head.
 *
 * Extending:
 * - A new Brand column is one more key here.
 *
 * @mixin Brand
 */
class BrandDetailResource extends JsonResource
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
            'linkedin' => $this->linkedin,
            'download' => $this->download,
            'catalog' => $this->catalog(),
            'content' => Document::make($this->content),
            'features' => Features::make($this->features),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'commentable' => (bool) $this->commentable,
            'comments_count' => $this->whenCounted('comments'),
            'seo' => new SeoResource($this->seoData()),
        ];
    }

    /**
     * The catalog file's path, full address, and file name, or null when there is none.
     *
     * @return array{path: string, url: string, name: string}|null
     */
    private function catalog(): ?array
    {
        $path = $this->resource->catalog;

        if (! is_string($path) || $path === '') {
            return null;
        }

        return [
            'path' => $path,
            'url' => url(Library::url($path)),
            'name' => basename($path),
        ];
    }
}
