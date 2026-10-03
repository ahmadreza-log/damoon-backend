<?php

namespace App\Filament\Resources\Galleries\Pages;

use App\Filament\Resources\Galleries\GalleryResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

/**
 * Edits a gallery. The kind stays as it was made. Delete stays on this page and on the list.
 *
 * Filament owns the getHeaderActions method name.
 */
class EditGallery extends EditRecord
{
    /** The resource whose form, model, and labels this page uses. */
    protected static string $resource = GalleryResource::class;

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
