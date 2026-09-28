<?php

namespace App\Filament\Resources\Categories\Pages;

use App\Filament\Resources\Categories\CategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

/**
 * Category index. The create button sits at the top of the page.
 *
 * Filament owns the getHeaderActions method name.
 */
class ListCategories extends ListRecords
{
    /** The resource whose table, model, and labels this page uses. */
    protected static string $resource = CategoryResource::class;

    /**
     * Actions above the list. Currently only create category.
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
