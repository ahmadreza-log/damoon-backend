<?php

namespace App\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Base controller.
 *
 * Put logic shared by API versions here, not inside one version's controller.
 *
 * Extending:
 * - A list a site usually needs whole returns paged() from its index and validates page and per_page up to CEILING.
 */
abstract class Controller
{
    /** Records on one page of a list that is whole by default, when per_page is left out. */
    public const SIZE = 50;

    /** The most records one page of such a list may hold. */
    public const CEILING = 100;

    /**
     * The whole list, or one page of it when the request sends page or per_page.
     *
     * A paged answer adds links and meta beside data, so a client that reads only data works either way.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  array<string, mixed>  $data
     * @return Collection<int, TModel>|LengthAwarePaginator<int, TModel>
     */
    protected function paged(Builder $query, array $data): Collection|LengthAwarePaginator
    {
        if (! isset($data['page']) && ! isset($data['per_page'])) {
            return $query->get();
        }

        return $query->paginate((int) ($data['per_page'] ?? self::SIZE))->withQueryString();
    }
}
