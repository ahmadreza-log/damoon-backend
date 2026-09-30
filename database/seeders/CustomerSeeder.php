<?php

namespace Database\Seeders;

use App\Models\Customer;
use Illuminate\Database\Seeder;

/**
 * Sample site customers with Persian names. Every password is "password".
 *
 * Extending:
 * - Change COUNT for more or fewer customers per run.
 */
class CustomerSeeder extends Seeder
{
    /** Customers added on each run. */
    public const COUNT = 15;

    /**
     * Creates the sample customers.
     */
    public function run(): void
    {
        Customer::factory(self::COUNT)->create();
    }
}
