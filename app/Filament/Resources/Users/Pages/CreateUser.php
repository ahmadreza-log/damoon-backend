<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\CreateRecord;

/**
 * Creates a user. The owner role is given only to the first account, and only inside the model.
 */
class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;
}
