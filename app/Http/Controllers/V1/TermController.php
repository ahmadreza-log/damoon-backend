<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\TermDetailResource;
use App\Http\Resources\V1\TermResource;
use App\Models\Category;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * The shared list and detail routes of categories and tags, version 1.
 *
 * Public and read-only. articles_count counts only published articles. The articles
 * themselves are listed by /v1/articles with the category or tag filter.
 *
 * Extending:
 * - A new article group extends this class, sets MODEL and MISSING, and gets its two routes in routes/api.php.
 * - Add a list filter to the validate rules in index so the OpenAPI document lists it.
 */
abstract class TermController extends Controller
{
    /** @var class-string<Category|Tag> */
    protected const MODEL = Category::class;

    /** The 404 message for an unknown slug. */
    protected const MISSING = '';

    /**
     * Records with their parent id and published article count, ordered by name.
     *
     * The whole list comes back by default. Send page or per_page (up to 100, 50 when left out)
     * to get it a page at a time, with links and meta. q searches the name. parent takes an id
     * and lists its children; 0 lists the top-level ones.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'parent' => ['nullable', 'integer', 'min:0'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.self::CEILING],
        ]);

        $query = static::MODEL::query()
            ->withCount(['articles' => fn ($query) => $query->published()])
            ->when($data['q'] ?? null, fn (Builder $query, string $text) => $query->where('name', 'like', '%'.$text.'%'))
            ->when(isset($data['parent']), fn (Builder $query) => (int) $data['parent'] === 0
                ? $query->whereNull('parent_id')
                : $query->where('parent_id', (int) $data['parent']))
            ->orderBy('name');

        return TermResource::collection($this->paged($query, $data));
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
