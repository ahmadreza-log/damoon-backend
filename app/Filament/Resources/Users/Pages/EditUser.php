<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\EditRecord;

/**
 * Edits a user. This form cannot move the owner role. The model keeps it in protect.
 */
class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;
}
