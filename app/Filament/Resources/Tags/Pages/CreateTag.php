<?php

namespace App\Filament\Resources\Tags\Pages;

use App\Filament\Resources\Tags\TagResource;
use Filament\Resources\Pages\CreateRecord;

/**
 * Creates a tag.
 */
class CreateTag extends CreateRecord
{
    /** The resource whose form, model, and labels this page uses. */
    protected static string $resource = TagResource::class;
}
