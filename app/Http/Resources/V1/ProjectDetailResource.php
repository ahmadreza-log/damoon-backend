<?php

namespace App\Http\Resources\V1;

use App\Models\Project;
use App\Support\Library;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One project with everything its site page shows.
 *
 * Besides the list fields: the description as a Tiptap JSON document through Document, the
 * services given, how long it took, the client's testimonial with its voice message, the
 * similar projects in list form, whether comments are open with the approved comment count,
 * and the resolved SEO for the page head.
 *
 * Extending:
 * - A new Project column is one more key here.
 *
 * @mixin Project
 */
class ProjectDetailResource extends JsonResource
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
            'duration' => $this->duration,
            /** @var list<string> */
            'services' => array_values(array_filter((array) $this->services, fn (mixed $item): bool => is_string($item) && $item !== '')),
            'testimonial' => $this->testimonial(),
            'content' => Document::make($this->content),
            'similar' => ProjectResource::collection($this->whenLoaded('similar')),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'commentable' => (bool) $this->commentable,
            'comments_count' => $this->whenCounted('comments'),
            'seo' => new SeoResource($this->seoData()),
        ];
    }

    /**
     * The client's name, position, words, and voice message, or null when none of them is filled.
     *
     * @return array{name: string|null, position: string|null, text: string|null, voice: array{path: string, url: string, name: string}|null}|null
     */
    private function testimonial(): ?array
    {
        $voice = $this->voice();

        if (blank($this->employer) && blank($this->position) && blank($this->testimony) && $voice === null) {
            return null;
        }

        return [
            'name' => $this->employer,
            'position' => $this->position,
            'text' => $this->testimony,
            'voice' => $voice,
        ];
    }

    /**
     * The voice message file's path, full address, and file name, or null when there is none.
     *
     * @return array{path: string, url: string, name: string}|null
     */
    private function voice(): ?array
    {
        $path = $this->resource->voice;

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
