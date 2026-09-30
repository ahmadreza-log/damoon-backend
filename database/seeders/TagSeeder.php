<?php

namespace Database\Seeders;

use App\Models\Tag;
use Database\Factories\TagFactory;
use Illuminate\Database\Seeder;

/**
 * One sample tag for each name in TagFactory::NAMES.
 *
 * A tag whose name already exists is kept, so running this again adds nothing new.
 *
 * Extending:
 * - Another sample tag is one more name in TagFactory::NAMES.
 */
class TagSeeder extends Seeder
{
    /**
     * Creates the sample tags.
     */
    public function run(): void
    {
        foreach (TagFactory::NAMES as $name) {
            Tag::query()->firstWhere('name', $name) ?? Tag::factory()->create(['name' => $name]);
        }
    }
}
