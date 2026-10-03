<?php

namespace App\Http\Resources\V1;

use App\Models\Article;
use App\Support\Seo;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One published article with everything its site page shows.
 *
 * Besides the list fields: the body as a Tiptap JSON document through Document, the gallery, questions, related articles
 * that are published too, whether visitors may send comments and how many approved ones there are,
 * and the resolved SEO for the page head. The comments themselves come from CommentController.
 *
 * Extending:
 * - A new Article column or relation is one more key here. Load the relation in ArticleController::show.
 *
 * @mixin Article
 */
class ArticleDetailResource extends JsonResource
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
            'commentable' => (bool) $this->commentable,
            'comments_count' => $this->whenCounted('comments'),
            'content' => Document::make($this->content),
            'gallery' => Picture::list($this->gallery),
            'questions' => Questions::make($this->questions),
            'related' => ArticleResource::collection($this->whenLoaded('related')),
            'seo' => new SeoResource($this->seoData(Seo::LOCALE)),
        ];
    }
}
