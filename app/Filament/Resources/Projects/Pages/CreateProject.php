<?php

namespace App\Filament\Resources\Projects\Pages;

use App\Filament\Resources\Projects\ProjectResource;
use Filament\Resources\Pages\CreateRecord;

/**
 * Creates a project. An empty slug is filled from the title on the model.
 *
 * Extending:
 * - Filament owns the resource property name.
 */
class CreateProject extends CreateRecord
{
    /** The resource whose form, model, and labels this page uses. */
    protected static string $resource = ProjectResource::class;
}
