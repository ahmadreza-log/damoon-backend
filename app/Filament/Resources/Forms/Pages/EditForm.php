<?php

namespace App\Filament\Resources\Forms\Pages;

use App\Filament\Resources\Forms\FormResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

/**
 * Edits a form and its fields. The form builder button and delete sit above the form.
 *
 * Filament owns the getHeaderActions method name.
 */
class EditForm extends EditRecord
{
    /** The resource whose form, model, and labels this page uses. */
    protected static string $resource = FormResource::class;

    /**
     * Actions above the form: open the form builder, and delete.
     *
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            FormResource::designer(),
            DeleteAction::make()
                ->modalDescription('پیام‌های این فرم و فایل‌هایشان هم حذف می‌شوند.'),
        ];
    }
}
