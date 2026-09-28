<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Filament\Resources\Customers\CustomerResource;
use Filament\Resources\Pages\EditRecord;

/**
 * Edits a customer profile. An empty password in Fields::account is not saved.
 */
class EditCustomer extends EditRecord
{
    /** The resource whose form, model, and labels this page uses. */
    protected static string $resource = CustomerResource::class;
}
