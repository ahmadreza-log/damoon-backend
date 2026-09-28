<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Fills a fresh database with sample data for local development.
 *
 * Run it with php artisan db:seed or migrate --seed. Real installs do not need it:
 * the /install page creates the owner account instead.
 *
 * Extending:
 * - Call other seeders from run() with $this->call([...]) as the sample data grows.
 */
class DatabaseSeeder extends Seeder
{
    /**
     * Creates one sample user with the factory defaults.
     *
     * The first user ever saved receives the owner role, so on an empty database
     * this sample user becomes the owner.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'username' => 'testuser',
            'email' => 'test@example.com',
            'firstname' => 'Test',
            'lastname' => 'User',
        ]);
    }
}
