<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\BrandDetailResource;
use App\Http\Resources\V1\BrandResource;
use App\Models\Brand;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Brands (برندها) for the site, version 1.
 *
 * Public and read-only: no token is needed. Routes live under /v1/brands.
 *
 * Extending:
 * - Add a list filter to the validate rules in index so the OpenAPI document lists it.
 * - A new detail field goes in BrandDetailResource.
 */
class BrandController extends Controller
{
    /** The most brands one page of the list may hold. */
    public const LIMIT = 50;

    /**
     * Brands ordered by title, a page at a time.
     *
     * q searches the title and the English title. per_page is 12 when left out and at most 50.
     *
     * @return AnonymousResourceCollection<LengthAwarePaginator<int, BrandResource>>
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.self::LIMIT],
        ]);

        $brands = Brand::query()
            ->when($data['q'] ?? null, fn (Builder $query, string $text) => $query->where(fn (Builder $inner) => $inner
                ->where('title', 'like', '%'.$text.'%')
                ->orWhere('english', 'like', '%'.$text.'%')))
            ->orderBy('title')
            ->orderBy('id')
            ->paginate((int) ($data['per_page'] ?? 12))
            ->withQueryString();

        return BrandResource::collection($brands);
    }

    /**
     * One brand by its slug, with its description, features, links, catalog, approved comment count, and SEO.
     *
     * An unknown slug gets 404.
     */
    public function show(string $slug): BrandDetailResource
    {
        $brand = Brand::query()
            ->where('slug', $slug)
            ->withCount(['comments' => fn ($query) => $query->approved()])
            ->first();

        abort_if($brand === null, 404, 'برند پیدا نشد.');

        return new BrandDetailResource($brand);
    }
}
