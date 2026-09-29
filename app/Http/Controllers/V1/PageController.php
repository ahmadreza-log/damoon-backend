<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\PageDetailResource;
use App\Http\Resources\V1\PageResource;
use App\Models\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Published pages (برگه‌ها) for the site, version 1.
 *
 * Public and read-only: no token is needed, and a page whose publish date is still to
 * come is never sent. Routes live under /v1/pages.
 *
 * Extending:
 * - Add a list filter to the validate rules in index so the OpenAPI document lists it.
 * - Load a new relation in show and send it from PageDetailResource.
 */
class PageController extends Controller
{
    /**
     * Published pages, ordered by parent and position, so a site can build its menu tree.
     *
     * The whole list comes back by default. Send page or per_page (up to 100, 50 when left out)
     * to get it a page at a time, with links and meta. q searches the title. parent takes a page
     * id and lists its children; 0 lists the top-level pages.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'parent' => ['nullable', 'integer', 'min:0'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.self::CEILING],
        ]);

        $query = Page::query()
            ->published()
            ->when($data['q'] ?? null, fn (Builder $query, string $text) => $query->where('title', 'like', '%'.$text.'%'))
            ->when(isset($data['parent']), fn (Builder $query) => (int) $data['parent'] === 0
                ? $query->whereNull('parent_id')
                : $query->where('parent_id', (int) $data['parent']))
            ->orderBy('parent_id')
            ->orderBy('position')
            ->orderBy('title');

        return PageResource::collection($this->paged($query, $data));
    }

    /**
     * One published page by its slug, with its body, page builder design, children, and SEO.
     *
     * An unknown or unpublished slug gets 404.
     */
    public function show(string $slug): PageDetailResource
    {
        $page = Page::query()
            ->published()
            ->where('slug', $slug)
            ->with([
                'parent' => fn ($query) => $query->published(),
                'children' => fn ($query) => $query->published()->orderBy('position')->orderBy('title'),
            ])
            ->withCount(['comments' => fn ($query) => $query->approved()])
            ->first();

        abort_if($page === null, 404, 'برگه پیدا نشد.');

        return new PageDetailResource($page);
    }
}
