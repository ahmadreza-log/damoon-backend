<?php

namespace App\Filament\Resources\Categories\Pages;

use App\Filament\Resources\Categories\CategoryResource;
use Filament\Resources\Pages\CreateRecord;

/**
 * Creates a category.
 */
class CreateCategory extends CreateRecord
{
    /** The resource whose form, model, and labels this page uses. */
    protected static string $resource = CategoryResource::class;
}
