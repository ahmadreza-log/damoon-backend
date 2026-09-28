<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\TermDetailResource;
use App\Http\Resources\V1\TermResource;
use App\Models\Category;
use App\Models\Tag;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * The shared list and detail routes of categories and tags, version 1.
 *
 * Public and read-only. articles_count counts only published articles. The articles
 * themselves are listed by /v1/articles with the category or tag filter.
 *
 * Extending:
 * - A new article group extends this class, sets MODEL and MISSING, and gets its two routes in routes/api.php.
 */
abstract class TermController extends Controller
{
    /** @var class-string<Category|Tag> */
    protected const MODEL = Category::class;

    /** The 404 message for an unknown slug. */
    protected const MISSING = '';

    /**
     * Every record with its parent id and published article count, ordered by name.
     */
    public function index(): AnonymousResourceCollection
    {
        $terms = static::MODEL::query()
            ->withCount(['articles' => fn ($query) => $query->published()])
            ->orderBy('name')
            ->get();

        return TermResource::collection($terms);
    }

    /**
     * One record by its slug, with its description, trail, parent, children, banners, and questions.
     *
     * An unknown slug gets 404.
     */
    public function show(string $slug): TermDetailResource
    {
        $term = static::MODEL::query()
            ->where('slug', $slug)
            ->withCount(['articles' => fn ($query) => $query->published()])
            ->with([
                'parent',
                'children' => fn ($query) => $query->withCount(['articles' => fn ($inner) => $inner->published()])->orderBy('name'),
            ])
            ->first();

        abort_if($term === null, 404, static::MISSING);

        return new TermDetailResource($term);
    }
}
