<?php

namespace Database\Seeders;

use App\Models\Brand;
use Database\Factories\BrandFactory;
use Illuminate\Database\Seeder;

/**
 * One sample brand for each name in BrandFactory::NAMES.
 *
 * A brand whose title already exists is kept, so running this again adds nothing new.
 *
 * Extending:
 * - Another sample brand is one more entry in BrandFactory::NAMES.
 */
class BrandSeeder extends Seeder
{
    /**
     * Creates the sample brands.
     */
    public function run(): void
    {
        foreach (BrandFactory::NAMES as $title => $english) {
            Brand::query()->firstWhere('title', $title) ?? Brand::factory()->create([
                'title' => $title,
                'english' => $english,
            ]);
        }
    }
}
