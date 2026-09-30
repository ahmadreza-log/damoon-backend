<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

/**
 * A small tree of sample categories: three parents, each with a few children.
 *
 * Parents also get two questions for their page. A category whose name already
 * exists is kept, so running this again adds nothing new.
 *
 * Extending:
 * - Another branch is one more entry in TREE.
 */
class CategorySeeder extends Seeder
{
    /**
     * Parent names and the names of their children.
     *
     * @var array<string, array<int, string>>
     */
    public const TREE = [
        'فناوری' => ['برنامه‌نویسی', 'هوش مصنوعی', 'موبایل'],
        'سبک زندگی' => ['سلامت', 'سفر', 'آشپزی'],
        'کسب‌وکار' => ['اقتصاد', 'بازاریابی'],
    ];

    /**
     * Creates the category tree.
     */
    public function run(): void
    {
        foreach (self::TREE as $name => $children) {
            $parent = Category::query()->firstWhere('name', $name) ?? Category::factory()->create([
                'name' => $name,
                'questions' => [
                    ['question' => 'در دسته‌ی '.$name.' چه می‌خوانیم؟', 'answer' => fake('fa_IR')->realText(150)],
                    ['question' => 'مطالب این دسته هر چند وقت به‌روز می‌شوند؟', 'answer' => fake('fa_IR')->realText(120)],
                ],
            ]);

            foreach ($children as $child) {
                Category::query()->firstWhere('name', $child) ?? Category::factory()->create([
                    'name' => $child,
                    'parent_id' => $parent->getKey(),
                ]);
            }
        }
    }
}
