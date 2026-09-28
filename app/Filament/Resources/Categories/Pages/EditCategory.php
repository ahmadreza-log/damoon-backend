<?php

namespace App\Filament\Resources\Categories\Pages;

use App\Filament\Resources\Categories\CategoryResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

/**
 * Edits a category. Delete stays on this page and on the list.
 *
 * Filament owns the getHeaderActions method name.
 */
class EditCategory extends EditRecord
{
    /** The resource whose form, model, and labels this page uses. */
    protected static string $resource = CategoryResource::class;

    /**
     * Actions above the form. Currently only delete.
     *
     * @return array<int, DeleteAction>
     */
    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->modalDescription('نوشته‌ها و زیردسته‌های این دسته حذف نمی‌شوند، فقط از آن جدا می‌شوند.'),
        ];
    }
}
