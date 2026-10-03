<?php

namespace App\Http\Resources\V1;

use App\Models\Page;
use App\Support\Seo;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One published page with everything its site page shows.
 *
 * Besides the list fields: the body as a Tiptap JSON document through Document, the
 * page builder layout as JSON through Design, the same layout as HTML and CSS, the
 * trail of parent titles, the parent and published children, whether visitors may send
 * comments and how many approved ones there are, and the resolved SEO. The comments
 * themselves come from CommentController.
 * A site can draw the design tree itself or print html with css.
 *
 * Extending:
 * - Components added to the page builder arrive in design on their own; see Design for extra data per type.
 *
 * @mixin Page
 */
class PageDetailResource extends JsonResource
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
            'commentable' => (bool) $this->commentable,
            'comments_count' => $this->whenCounted('comments'),
            'content' => Document::make($this->content),
            'trail' => $this->trail(),
            'parent' => new PageResource($this->whenLoaded('parent')),
            'children' => PageResource::collection($this->whenLoaded('children')),
            'design' => Design::make($this->design),
            'html' => self::absolute((string) $this->markup),
            'css' => self::absolute((string) $this->style),
            'seo' => new SeoResource($this->seoData(Seo::LOCALE)),
        ];
    }

    /**
     * Builder HTML or CSS with library addresses made full, so it works on another host.
     */
    private static function absolute(string $code): string
    {
        return (string) preg_replace_callback(
            '~(["\'(])/storage/~',
            fn (array $match): string => $match[1].url('/storage').'/',
            $code,
        );
    }
}
