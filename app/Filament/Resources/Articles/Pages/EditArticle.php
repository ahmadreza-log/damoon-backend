<?php

namespace App\Filament\Resources\Articles\Pages;

use App\Filament\Resources\Articles\ArticleResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

/**
 * Edits an article. Delete stays on this page and on the list.
 *
 * Filament owns the getHeaderActions method name.
 */
class EditArticle extends EditRecord
{
    protected static string $resource = ArticleResource::class;

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
