<?php

namespace App\Filament\Resources\Brands\Pages;

use App\Filament\Resources\Brands\BrandResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

/**
 * Brand index. The create button sits at the top of the page.
 *
 * Filament owns the getHeaderActions method name.
 */
class ListBrands extends ListRecords
{
    /** The resource whose table, model, and labels this page uses. */
    protected static string $resource = BrandResource::class;

    /**
     * Actions above the list. Currently only create brand.
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
