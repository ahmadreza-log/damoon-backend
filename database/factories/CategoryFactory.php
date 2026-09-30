<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Builds fake article categories for tests and local sample data.
 *
 * Names are Persian topics and the description is Persian text. The slug is filled
 * from the name by the model, with a number added when it is taken.
 *
 * Extending:
 * - A new categories column needs a default here so Category::factory()->create() keeps working.
 *
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    /** Persian topics a sample category is named after. */
    public const NAMES = ['فناوری', 'برنامه‌نویسی', 'هوش مصنوعی', 'موبایل', 'سلامت', 'ورزش', 'اقتصاد', 'سفر', 'آشپزی', 'فرهنگ و هنر', 'آموزش', 'کسب‌وکار'];

    /**
     * The default column values for one fake category.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake('fa_IR')->randomElement(self::NAMES),
            'description' => fake('fa_IR')->realText(160),
            'parent_id' => null,
            'banners' => null,
            'questions' => null,
        ];
    }
}
