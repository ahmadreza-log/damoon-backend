<?php

namespace App\Http\Resources\V1;

use App\Models\Article;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A published article in a list: title, slug, a short excerpt, cover, author, and its groups.
 *
 * The excerpt is the start of the body as plain text. Only the author's name is sent,
 * never their account details. categories and tags are sent when they were loaded.
 *
 * Extending:
 * - A field every list needs goes here; one only the detail needs goes in ArticleDetailResource.
 *
 * @mixin Article
 */
class ArticleResource extends JsonResource
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
            'cover' => Picture::make($this->cover),
            'author' => $this->whenLoaded('author', fn (): ?string => $this->author?->getFilamentName()),
            'categories' => TermResource::collection($this->whenLoaded('categories')),
            'tags' => TermResource::collection($this->whenLoaded('tags')),
            'published_at' => $this->published_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
