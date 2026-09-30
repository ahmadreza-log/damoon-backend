<?php

namespace App\Filament\Resources\Forms\Pages;

use App\Filament\Resources\Forms\FormResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

/**
 * Form index. The create button sits at the top of the page.
 *
 * Filament owns the getHeaderActions method name.
 */
class ListForms extends ListRecords
{
    /** The resource whose table, model, and labels this page uses. */
    protected static string $resource = FormResource::class;

    /**
     * Actions above the list. Currently only create form.
     *
     * @return array<int, CreateAction>
     */
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
