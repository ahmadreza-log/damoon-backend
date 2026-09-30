<?php

namespace Database\Factories;

use App\Models\Page;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Builds fake site pages for tests and local sample data.
 *
 * Each page gets a Persian title, the same kind of HTML body as an article, and a
 * publish date in the past. It sits at the top level unless a parent is given. The
 * author is a new staff user unless one is given.
 *
 * Extending:
 * - A new pages column needs a default here so Page::factory()->create() keeps working.
 *
 * @extends Factory<Page>
 */
class PageFactory extends Factory
{
    /** Persian titles a sample page is named after. */
    public const TITLES = ['درباره ما', 'تماس با ما', 'قوانین و مقررات', 'حریم خصوصی', 'سوالات متداول', 'همکاری با ما'];

    /**
     * The default column values for one fake page.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake('fa_IR')->randomElement(self::TITLES),
            'content' => ArticleFactory::body(),
            'parent_id' => null,
            'position' => 0,
            'author_id' => User::factory(),
            'published_at' => fake()->dateTimeBetween('-6 months', '-1 day'),
            'commentable' => true,
        ];
    }
}
