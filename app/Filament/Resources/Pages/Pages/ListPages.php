<?php

namespace App\Filament\Resources\Pages\Pages;

use App\Filament\Resources\Pages\PageResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

/**
 * Page index. The create button sits at the top of the page.
 *
 * Filament owns the getHeaderActions method name.
 */
class ListPages extends ListRecords
{
    /** The resource whose table, model, and labels this page uses. */
    protected static string $resource = PageResource::class;

    /**
     * Actions above the list. Currently only create page.
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
