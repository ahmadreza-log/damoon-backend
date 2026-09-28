<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\PageDetailResource;
use App\Http\Resources\V1\PageResource;
use App\Models\Page;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Published pages (برگه‌ها) for the site, version 1.
 *
 * Public and read-only: no token is needed, and a page whose publish date is still to
 * come is never sent. Routes live under /v1/pages.
 *
 * Extending:
 * - Load a new relation in show and send it from PageDetailResource.
 */
class PageController extends Controller
{
    /**
     * Every published page, ordered by parent and position, so a site can build its menu tree.
     */
    public function index(): AnonymousResourceCollection
    {
        $pages = Page::query()
            ->published()
            ->orderBy('parent_id')
            ->orderBy('position')
            ->orderBy('title')
            ->get();

        return PageResource::collection($pages);
    }

    /**
     * One published page by its slug, with its body, page builder blocks, children, and SEO.
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
                'pageBuilderBlocks',
            ])
            ->first();

        abort_if($page === null, 404, 'برگه پیدا نشد.');

        return new PageDetailResource($page);
    }
}
