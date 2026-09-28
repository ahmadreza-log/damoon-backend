<?php

namespace App\Filament\Resources\Tags\Pages;

use App\Filament\Resources\Tags\TagResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

/**
 * Edits a tag. Delete stays on this page and on the list.
 *
 * Filament owns the getHeaderActions method name.
 */
class EditTag extends EditRecord
{
    /** The resource whose form, model, and labels this page uses. */
    protected static string $resource = TagResource::class;

    /**
     * Actions above the form. Currently only delete.
     *
     * @return array<int, DeleteAction>
     */
    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->modalDescription('نوشته‌ها و زیربرچسب‌های این برچسب حذف نمی‌شوند، فقط از آن جدا می‌شوند.'),
        ];
    }
}
