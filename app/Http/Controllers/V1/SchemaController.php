<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;

/**
 * Site-wide structured data from the general settings, version 1.
 *
 * Articles, pages, brands, and projects already carry their schemas in seo.schema, the
 * site-wide ones included. This route is for pages without such a record, like the home
 * page and the lists. Routes live under /v1/schema.
 *
 * Extending:
 * - Schema types and placeholders belong in the damoon/schema package (Damoon\Schema\Vocabulary).
 */
class SchemaController extends Controller
{
    /**
     * The active site-wide schemas as JSON-LD documents, with placeholders filled from the settings.
     *
     * data is a list of schema.org documents, each with @context and @type, ready to be
     * written into one script tag of type application/ld+json. Until the site-wide schemas
     * are edited, they are an Organization and a WebSite filled from the general settings.
     */
    public function index(): JsonResponse
    {
        return response()->json(['data' => Setting::current()?->structured() ?? []]);
    }
}
