<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Filament\Resources\Customers\CustomerResource;
use Filament\Resources\Pages\CreateRecord;

/**
 * Creates a customer from the panel. The customer signs in through the API and AuthController.
 */
class CreateCustomer extends CreateRecord
{
    protected static string $resource = CustomerResource::class;
}
