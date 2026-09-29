<?php

namespace App\Filament\Resources\Comments\Pages;

use App\Filament\Resources\Comments\CommentResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

/**
 * Edits a comment's name, email, text, and status. Answer and delete sit above the form.
 *
 * Filament owns the getHeaderActions method name.
 */
class EditComment extends EditRecord
{
    /** The resource whose form, model, and labels this page uses. */
    protected static string $resource = CommentResource::class;

    /**
     * Actions above the form: answer and delete.
     *
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            CommentResource::reply(),
            DeleteAction::make()
                ->modalDescription('پاسخ‌های این دیدگاه هم حذف می‌شوند.'),
        ];
    }
}
