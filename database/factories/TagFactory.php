<?php

namespace Database\Factories;

use App\Models\Tag;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Builds fake article tags for tests and local sample data.
 *
 * Names are short Persian labels. The slug is filled from the name by the model,
 * with a number added when it is taken.
 *
 * Extending:
 * - A new tags column needs a default here so Tag::factory()->create() keeps working.
 *
 * @extends Factory<Tag>
 */
class TagFactory extends Factory
{
    /** Persian labels a sample tag is named after. */
    public const NAMES = ['لاراول', 'پی‌اچ‌پی', 'جاوااسکریپت', 'اندروید', 'آیفون', 'امنیت', 'طراحی', 'سئو', 'استارتاپ', 'راهنما', 'نقد و بررسی', 'اخبار'];

    /**
     * The default column values for one fake tag.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake('fa_IR')->randomElement(self::NAMES),
            'description' => fake('fa_IR')->realText(120),
            'parent_id' => null,
            'banners' => null,
            'questions' => null,
        ];
    }
}
