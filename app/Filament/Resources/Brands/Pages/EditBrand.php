<?php

namespace App\Filament\Resources\Brands\Pages;

use App\Filament\Resources\Brands\BrandResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

/**
 * Edits a brand. Delete stays on this page and on the list.
 *
 * Filament owns the getHeaderActions method name.
 */
class EditBrand extends EditRecord
{
    /** The resource whose form, model, and labels this page uses. */
    protected static string $resource = BrandResource::class;

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
