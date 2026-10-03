<?php

namespace App\Filament\Resources\Galleries\Pages;

use App\Filament\Resources\Galleries\GalleryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

/**
 * Gallery index. The create button sits at the top of the page.
 *
 * Filament owns the getHeaderActions method name.
 */
class ListGalleries extends ListRecords
{
    /** The resource whose table, model, and labels this page uses. */
    protected static string $resource = GalleryResource::class;

    /**
     * Actions above the list. Currently only create gallery.
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
