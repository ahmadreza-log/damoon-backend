<?php

namespace App\Http\Controllers\V1;

use App\Models\Tag;

/**
 * Article tags (برچسب‌ها) for the site, version 1. Routes live under /v1/tags.
 *
 * Extending:
 * - The routes are shared with categories in TermController.
 */
class TagController extends TermController
{
    protected const MODEL = Tag::class;

    protected const MISSING = 'برچسب پیدا نشد.';
}
