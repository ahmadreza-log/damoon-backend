<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * Fills the database with Persian sample data for local development.
 *
 * Run it with php artisan db:seed or migrate --seed. On an empty database it also
 * marks install as finished, and testuser (password "password") becomes the owner.
 * Real installs do not need it: the /install page creates the owner account instead.
 * Roles, categories, tags, pages, brands, projects, forms, and testuser are kept when they already exist, so
 * running it on a database with data only adds staff, customers, articles, and comments on new content.
 *
 * Extending:
 * - A new sample seeder goes in the list in run(), after the seeders whose rows it reads.
 */
class DatabaseSeeder extends Seeder
{
    /**
     * Marks install as finished when needed, then runs every sample seeder in order.
     */
    public function run(): void
    {
        if (! Setting::installed()) {
            Setting::query()->create([
                'title' => 'دامون',
                'description' => 'سامانه‌ی مدیریت محتوای دامون',
                'installed_at' => now(),
            ]);
        }

        $this->call([
            RoleSeeder::class,
            UserSeeder::class,
            CustomerSeeder::class,
            CategorySeeder::class,
            TagSeeder::class,
            ArticleSeeder::class,
            PageSeeder::class,
            BrandSeeder::class,
            ProjectSeeder::class,
            CommentSeeder::class,
            FormSeeder::class,
        ]);
    }
}
