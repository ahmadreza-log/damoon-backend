<?php

namespace App\Filament\Resources\Pages\Pages;

use App\Filament\Resources\Pages\PageResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

/**
 * Edits a page. The page builder and delete sit above the form.
 *
 * Filament owns the getHeaderActions method name.
 */
class EditPage extends EditRecord
{
    /** The resource whose form, model, and labels this page uses. */
    protected static string $resource = PageResource::class;

    /**
     * Actions above the form: open the page builder, and delete.
     *
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            PageResource::designer(),
            DeleteAction::make(),
        ];
    }
}
