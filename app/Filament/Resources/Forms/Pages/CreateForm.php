<?php

namespace App\Filament\Resources\Forms\Pages;

use App\Filament\Resources\Forms\FormResource;
use Filament\Resources\Pages\CreateRecord;

/**
 * Creates a form, then opens it in the form builder. An empty slug is filled from the title on the model.
 *
 * Extending:
 * - Filament owns the resource property and getRedirectUrl method names.
 */
class CreateForm extends CreateRecord
{
    /** The resource whose form, model, and labels this page uses. */
    protected static string $resource = FormResource::class;

    /**
     * The form builder of the new form, so its fields are laid out next.
     */
    protected function getRedirectUrl(): string
    {
        return FormResource::getUrl('design', ['record' => $this->getRecord()]);
    }
}
