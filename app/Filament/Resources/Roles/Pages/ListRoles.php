<?php

namespace App\Filament\Resources\Roles\Pages;

use App\Filament\Resources\Roles\RoleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

/**
 * Role index. The create button sits at the top of the page.
 *
 * Filament owns the getHeaderActions method name.
 */
class ListRoles extends ListRecords
{
    protected static string $resource = RoleResource::class;

    /**
     * Actions above the list. Currently only create role.
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
