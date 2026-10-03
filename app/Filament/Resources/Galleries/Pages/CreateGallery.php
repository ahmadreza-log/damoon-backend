<?php

namespace App\Filament\Resources\Galleries\Pages;

use App\Filament\Resources\Galleries\GalleryResource;
use Filament\Resources\Pages\CreateRecord;

/**
 * Creates a gallery. An empty slug is filled from the title on the model.
 *
 * Extending:
 * - Filament owns the resource property name.
 */
class CreateGallery extends CreateRecord
{
    /** The resource whose form, model, and labels this page uses. */
    protected static string $resource = GalleryResource::class;
}
