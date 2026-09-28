<?php

namespace App\Filament\Resources\Pages\Pages;

use App\Filament\Resources\Pages\PageResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

/**
 * Edits a page. Delete stays on this page and on the list.
 *
 * Filament owns the getHeaderActions method name.
 */
class EditPage extends EditRecord
{
    /** The resource whose form, model, and labels this page uses. */
    protected static string $resource = PageResource::class;

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
