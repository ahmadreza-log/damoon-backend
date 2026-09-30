<?php

namespace App\Filament\Resources\Projects\Pages;

use App\Filament\Resources\Projects\ProjectResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

/**
 * Project index. The create button sits at the top of the page.
 *
 * Filament owns the getHeaderActions method name.
 */
class ListProjects extends ListRecords
{
    /** The resource whose table, model, and labels this page uses. */
    protected static string $resource = ProjectResource::class;

    /**
     * Actions above the list. Currently only create project.
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
