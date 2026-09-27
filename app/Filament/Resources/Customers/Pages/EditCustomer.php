<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Filament\Resources\Customers\CustomerResource;
use Filament\Resources\Pages\EditRecord;

/**
 * Edits a customer profile. An empty password in Fields::account is not saved.
 */
class EditCustomer extends EditRecord
{
    protected static string $resource = CustomerResource::class;
}
