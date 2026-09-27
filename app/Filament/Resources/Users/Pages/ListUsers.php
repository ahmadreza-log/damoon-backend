<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

/**
 * User index. The create button sits at the top of the page.
 *
 * Filament owns the getHeaderActions method name.
 */
class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    /**
     * Actions above the list. Currently only create user.
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
