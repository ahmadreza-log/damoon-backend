<?php

namespace App\Filament\Resources\Brands\Pages;

use App\Filament\Resources\Brands\BrandResource;
use Filament\Resources\Pages\CreateRecord;

/**
 * Creates a brand. An empty slug is filled from the title on the model.
 *
 * Extending:
 * - Filament owns the resource property name.
 */
class CreateBrand extends CreateRecord
{
    /** The resource whose form, model, and labels this page uses. */
    protected static string $resource = BrandResource::class;
}
