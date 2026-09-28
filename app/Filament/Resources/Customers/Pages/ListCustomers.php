<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Filament\Resources\Customers\CustomerResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

/**
 * Customer index. The create button sits at the top of the page.
 *
 * Filament owns the getHeaderActions method name.
 */
class ListCustomers extends ListRecords
{
    /** The resource whose table, model, and labels this page uses. */
    protected static string $resource = CustomerResource::class;

    /**
     * Actions above the list. Currently only create customer.
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
