<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\ArticleDetailResource;
use App\Http\Resources\V1\ArticleResource;
use App\Models\Article;
use App\Models\Category;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Published articles (نوشته‌ها) for the site, version 1.
 *
 * Public and read-only: no token is needed, and an article whose publish date is still
 * to come is never sent. Routes live under /v1/articles.
 *
 * Extending:
 * - Add a list filter to the validate rules in index so the OpenAPI document lists it.
 * - Load a new relation in show and send it from ArticleDetailResource.
 */
class ArticleController extends Controller
{
    /** The most articles one page of the list may hold. */
    public const LIMIT = 50;

    /**
     * Published articles, newest first, a page at a time.
     *
     * q searches the title. category and tag take a slug; a category also brings the
     * articles of the categories under it. An unknown slug gives an empty list.
     *
     * @return AnonymousResourceCollection<LengthAwarePaginator<int, ArticleResource>>
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:255'],
            'tag' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.self::LIMIT],
        ]);

        $query = Article::query()
            ->published()
            ->with(['author', 'categories', 'tags'])
            ->when($data['q'] ?? null, fn (Builder $query, string $text) => $query->where('title', 'like', '%'.$text.'%'))
            ->when($data['category'] ?? null, function (Builder $query, string $slug): void {
                $category = Category::query()->where('slug', $slug)->first();
                $query->whereHas('categories', fn (Builder $inner) => $inner->whereIn('categories.id', $category?->family() ?? []));
            })
            ->when($data['tag'] ?? null, function (Builder $query, string $slug): void {
                $tag = Tag::query()->where('slug', $slug)->first();
                $query->whereHas('tags', fn (Builder $inner) => $inner->whereIn('tags.id', $tag?->family() ?? []));
            })
            ->orderByDesc('published_at')
            ->orderByDesc('id');

        $articles = $query->paginate((int) ($data['per_page'] ?? 12))->withQueryString();

        return ArticleResource::collection($articles);
    }

    /**
     * One published article by its slug, with its body, gallery, questions, related articles, and SEO.
     *
     * An unknown or unpublished slug gets 404.
     */
    public function show(string $slug): ArticleDetailResource
    {
        $article = Article::query()
            ->published()
            ->where('slug', $slug)
            ->with([
                'author',
                'categories',
                'tags',
                'related' => fn ($query) => $query->published()->with(['author', 'categories', 'tags']),
            ])
            ->first();

        abort_if($article === null, 404, 'نوشته پیدا نشد.');

        return new ArticleDetailResource($article);
    }
}
