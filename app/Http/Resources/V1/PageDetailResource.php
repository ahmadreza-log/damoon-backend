<?php

namespace App\Http\Resources\V1;

use App\Filament\Builder\Block;
use App\Models\Page;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;
use Redberry\PageBuilderPlugin\Models\PageBuilderBlock;

/**
 * One published page with everything its site page shows.
 *
 * Besides the list fields: the body as a Tiptap JSON document through Document, the
 * page builder blocks, the whole builder layout as HTML, the trail of parent titles,
 * the parent and published children, and the resolved SEO. Each block is sent both as
 * data, with picture paths turned into addresses and rich text turned into a Tiptap
 * JSON document, and as the HTML the site views draw, so a site can either draw
 * blocks itself or print the HTML.
 *
 * Extending:
 * - A new page builder block shows up here on its own once it is in Page::BUILDER.
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
        $blocks = $this->pageBuilderBlocks
            ->sortBy('order')
            ->filter(fn (PageBuilderBlock $block): bool => in_array($block->block_type, Page::BUILDER, true))
            ->map(fn (PageBuilderBlock $block): array => self::block($block))
            ->values()
            ->all();

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
            'content' => Document::make($this->content),
            'trail' => $this->trail(),
            'parent' => new PageResource($this->whenLoaded('parent')),
            'children' => PageResource::collection($this->whenLoaded('children')),
            'blocks' => $blocks,
            'layout' => implode("\n", array_column($blocks, 'html')),
            'seo' => new SeoResource($this->seoData()),
        ];
    }

    /**
     * One block: its id, type such as hero, Persian name, data, and HTML.
     *
     * @return array{id: string, type: string, name: string, data: array<string, mixed>, html: string}
     */
    private static function block(PageBuilderBlock $block): array
    {
        /** @var class-string<Block> $type */
        $type = $block->block_type;
        $data = (array) $block->data;

        foreach ($type::images() as $key) {
            if (! array_key_exists($key, $data)) {
                continue;
            }

            $data[$key] = is_array($data[$key]) ? Picture::list($data[$key]) : Picture::make($data[$key]);
        }

        foreach ($type::documents() as $key) {
            if (array_key_exists($key, $data)) {
                $data[$key] = Document::make($data[$key]);
            }
        }

        return [
            'id' => (string) $block->id,
            'type' => Str::kebab(class_basename($type)),
            'name' => $type::label(),
            'data' => $data,
            'html' => Page::draw($block),
        ];
    }
}
