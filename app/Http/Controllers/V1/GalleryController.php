<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\GalleryDetailResource;
use App\Http\Resources\V1\GalleryResource;
use App\Models\Gallery;
use App\Models\Kind;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;

/**
 * Galleries (گالری‌ها) for the site, version 1: picture, video, and audio galleries.
 *
 * Public and read-only. Routes live under /v1/galleries.
 *
 * Extending:
 * - Add a list filter to the validate rules in index so the OpenAPI document lists it.
 * - A new detail field goes in GalleryDetailResource.
 */
class GalleryController extends Controller
{
    /** The most galleries one page of the list may hold. */
    public const LIMIT = 50;

    /**
     * Galleries, newest first, a page at a time.
     *
     * q searches the title and description. kind keeps only picture (image), video, or audio
     * galleries. per_page is 12 when left out and at most 50.
     *
     * @return AnonymousResourceCollection<LengthAwarePaginator<int, GalleryResource>>
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'kind' => ['nullable', Rule::enum(Kind::class)],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.self::LIMIT],
        ]);

        $galleries = Gallery::query()
            ->when($data['kind'] ?? null, fn (Builder $query, string $kind) => $query->where('kind', $kind))
            ->when($data['q'] ?? null, fn (Builder $query, string $text) => $query->where(fn (Builder $inner) => $inner
                ->where('title', 'like', '%'.$text.'%')
                ->orWhere('description', 'like', '%'.$text.'%')))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate((int) ($data['per_page'] ?? 12))
            ->withQueryString();

        return GalleryResource::collection($galleries);
    }

    /**
     * One gallery by its slug, with its files in order.
     *
     * An unknown slug gets 404.
     */
    public function show(string $slug): GalleryDetailResource
    {
        $gallery = Gallery::query()->where('slug', $slug)->first();

        abort_if($gallery === null, 404, 'گالری پیدا نشد.');

        return new GalleryDetailResource($gallery);
    }
}
