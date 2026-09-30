<?php

namespace App\Filament\Resources\Projects\Pages;

use App\Filament\Resources\Projects\ProjectResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

/**
 * Edits a project. Delete stays on this page and on the list.
 *
 * Filament owns the getHeaderActions method name.
 */
class EditProject extends EditRecord
{
    /** The resource whose form, model, and labels this page uses. */
    protected static string $resource = ProjectResource::class;

    /**
     * Actions above the form. Currently only delete.
     *
     * @return array<int, DeleteAction>
     */
    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
