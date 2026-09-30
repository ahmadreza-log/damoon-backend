<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\ProjectDetailResource;
use App\Http\Resources\V1\ProjectResource;
use App\Models\Project;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Projects (پروژه‌ها) for the site, version 1.
 *
 * Public and read-only: no token is needed. Routes live under /v1/projects.
 * Comments on a project are under /v1/projects/{slug}/comments.
 *
 * Extending:
 * - Add a list filter to the validate rules in index so the OpenAPI document lists it.
 * - A new detail field goes in ProjectDetailResource.
 */
class ProjectController extends Controller
{
    /** The most projects one page of the list may hold. */
    public const LIMIT = 50;

    /**
     * Projects, newest year first, a page at a time.
     *
     * q searches the title, industry, and location. per_page is 12 when left out and at most 50.
     *
     * @return AnonymousResourceCollection<LengthAwarePaginator<int, ProjectResource>>
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.self::LIMIT],
        ]);

        $projects = Project::query()
            ->when($data['q'] ?? null, fn (Builder $query, string $text) => $query->where(fn (Builder $inner) => $inner
                ->where('title', 'like', '%'.$text.'%')
                ->orWhere('industry', 'like', '%'.$text.'%')
                ->orWhere('location', 'like', '%'.$text.'%')))
            ->orderByRaw('year is null')
            ->orderByDesc('year')
            ->orderBy('title')
            ->orderBy('id')
            ->paginate((int) ($data['per_page'] ?? 12))
            ->withQueryString();

        return ProjectResource::collection($projects);
    }

    /**
     * One project by its slug, with its description, services, client testimonial, similar projects,
     * approved comment count, and SEO.
     *
     * An unknown slug gets 404.
     */
    public function show(string $slug): ProjectDetailResource
    {
        $project = Project::query()
            ->where('slug', $slug)
            ->with(['similar' => fn ($query) => $query->orderByDesc('year')->orderBy('title')])
            ->withCount(['comments' => fn ($query) => $query->approved()])
            ->first();

        abort_if($project === null, 404, 'پروژه پیدا نشد.');

        return new ProjectDetailResource($project);
    }
}
