<?php

namespace App\Http\Controllers\V1;

use App\Models\Category;

/**
 * Article categories (دسته‌بندی‌ها) for the site, version 1. Routes live under /v1/categories.
 *
 * Extending:
 * - The routes are shared with tags in TermController.
 */
class CategoryController extends TermController
{
    protected const MODEL = Category::class;

    protected const MISSING = 'دسته‌بندی پیدا نشد.';
}
